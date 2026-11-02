<?php

namespace App\Services;

use App\Models\DocumentSeries;
use App\Models\FinancialYear;
use App\Support\TransactionTypes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceNumberGenerator
{
    /** @deprecated Use TransactionTypes::prefixes() — kept for older callers/tests. */
    public const PREFIXES = [
        'sale' => 'INV',
        'purchase' => 'PUR',
        'credit_note' => 'CN',
    ];

    /**
     * Active formats for a document type in the financial year of $date.
     *
     * @return list<array{id:int,name:string,prefix:string,preview:string,is_default:bool}>
     */
    public function formatsFor(string $docType, Carbon|string|null $date = null): array
    {
        $fy = FinancialYear::forDate($date);
        $rows = DocumentSeries::query()
            ->where('financial_year_id', $fy->id)
            ->where('doc_type', $docType)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        return $rows->map(fn (DocumentSeries $s) => [
            'id' => (int) $s->id,
            'name' => (string) ($s->name ?: $s->prefix),
            'prefix' => (string) $s->prefix,
            'preview' => $this->formatNumber($s, $fy, (int) $s->next_number),
            'is_default' => (bool) $s->is_default,
        ])->all();
    }

    /**
     * Preview the next number without incrementing the counter.
     *
     * @return array{number:string,format_id:int,format_name:string,formats:list<array{id:int,name:string,prefix:string,preview:string,is_default:bool}>}
     */
    public function peek(string $docType, Carbon|string|null $date = null, ?int $seriesId = null): array
    {
        $fy = FinancialYear::forDate($date);
        $series = $this->resolveSeries($docType, $fy->id, $seriesId, lock: false);
        $formats = $this->formatsFor($docType, $date);

        return [
            'number' => $this->formatNumber($series, $fy, (int) $series->next_number),
            'format_id' => (int) $series->id,
            'format_name' => (string) ($series->name ?: $series->prefix),
            'formats' => $formats,
        ];
    }

    /**
     * Allocate the next number for a document type in the financial year of $date.
     * Uses the default active format for that type (multi-series supported).
     * Pass $seriesId to force a specific format row.
     */
    public function next(string $docType, Carbon|string|null $date = null, ?int $seriesId = null): string
    {
        return DB::transaction(function () use ($docType, $date, $seriesId) {
            $fy = FinancialYear::forDate($date);
            $series = $this->resolveSeries($docType, $fy->id, $seriesId, lock: true);

            $number = (int) $series->next_number;
            $series->update(['next_number' => $number + 1]);

            return $this->formatNumber($series, $fy, $number);
        });
    }

    private function formatNumber(DocumentSeries $series, FinancialYear $fy, int $number): string
    {
        return sprintf(
            '%s/%s/%s',
            $series->prefix,
            $fy->name,
            str_pad((string) $number, (int) $series->padding, '0', STR_PAD_LEFT)
        );
    }

    private function resolveSeries(string $docType, int $fyId, ?int $seriesId, bool $lock): DocumentSeries
    {
        $query = DocumentSeries::where('financial_year_id', $fyId)
            ->where('doc_type', $docType)
            ->where('is_active', true);

        if ($lock) {
            $query->lockForUpdate();
        }

        if ($seriesId) {
            $series = (clone $query)->where('id', $seriesId)->first();
            if (! $series) {
                throw ValidationException::withMessages([
                    'document_series_id' => __('Pick a number series that covers this date.'),
                ]);
            }

            return $series;
        }

        $series = (clone $query)->where('is_default', true)->first()
            ?? (clone $query)->orderBy('id')->first();

        if ($series) {
            return $series;
        }

        $prefix = TransactionTypes::prefixes()[$docType]
            ?? self::PREFIXES[$docType]
            ?? strtoupper(substr($docType, 0, 3));

        $created = DocumentSeries::create([
            'doc_type' => $docType,
            'financial_year_id' => $fyId,
            'name' => TransactionTypes::label($docType),
            'prefix' => $prefix,
            'next_number' => 1,
            'padding' => 4,
            'is_default' => true,
            'is_active' => true,
        ]);

        if ($lock) {
            return DocumentSeries::where('id', $created->id)->lockForUpdate()->firstOrFail();
        }

        return $created;
    }
}
