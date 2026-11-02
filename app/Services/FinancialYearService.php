<?php

namespace App\Services;

use App\Models\DocumentSeries;
use App\Models\FinancialYear;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Catalog + activate FY; seeds document series like TMS/ERP Transaction Master. */
class FinancialYearService
{
    public function __construct(private InvoiceNumberGenerator $numbers) {}

    /** Suggested next April–March year (India). */
    public function suggest(?FinancialYear $after = null): array
    {
        if ($after) {
            $start = $after->end_date->copy()->addDay();
        } else {
            $latest = FinancialYear::orderByDesc('end_date')->first();
            if ($latest) {
                $start = $latest->end_date->copy()->addDay();
            } else {
                $y = now()->month >= 4 ? now()->year : now()->year - 1;
                $start = Carbon::create($y, 4, 1);
            }
        }

        $end = $start->copy()->addYear()->subDay();

        return [
            'name' => $start->year.'-'.substr((string) ($start->year + 1), -2),
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ];
    }

    public function create(array $data, bool $makeCurrent = false, bool $seedSeries = true): FinancialYear
    {
        $this->assertNoOverlap($data['start_date'], $data['end_date']);

        return DB::transaction(function () use ($data, $makeCurrent, $seedSeries) {
            $fy = FinancialYear::create([
                'name' => $data['name'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_current' => false,
            ]);

            if ($seedSeries) {
                $this->seedSeries($fy);
            }
            if ($makeCurrent) {
                $this->activate($fy);
            }

            return $fy->fresh();
        });
    }

    public function update(FinancialYear $fy, array $data): FinancialYear
    {
        $this->assertNoOverlap($data['start_date'], $data['end_date'], $fy->id);
        $fy->update([
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
        ]);

        return $fy->fresh();
    }

    public function activate(FinancialYear $fy): FinancialYear
    {
        return DB::transaction(function () use ($fy) {
            FinancialYear::query()->update(['is_current' => false]);
            $fy->update(['is_current' => true]);
            $this->seedSeries($fy);

            return $fy->fresh();
        });
    }

    public function createNext(FinancialYear $from, bool $activate = false): FinancialYear
    {
        $suggest = $this->suggest($from);
        if (FinancialYear::where('name', $suggest['name'])->exists()) {
            throw ValidationException::withMessages(['name' => __('That financial year already exists.')]);
        }

        return $this->create($suggest, $activate, true);
    }

    public function delete(FinancialYear $fy): void
    {
        if ($fy->is_current) {
            throw ValidationException::withMessages(['delete' => __('Activate another year before deleting the current one.')]);
        }
        if ($fy->documentSeries()->exists() && (
            DB::table('sales')->where('financial_year_id', $fy->id)->exists()
            || DB::table('purchases')->where('financial_year_id', $fy->id)->exists()
            || DB::table('sale_returns')->where('financial_year_id', $fy->id)->exists()
            || DB::table('ledger_entries')->where('financial_year_id', $fy->id)->exists()
        )) {
            throw ValidationException::withMessages(['delete' => __('This financial year has vouchers and cannot be deleted.')]);
        }

        $fy->documentSeries()->delete();
        $fy->delete();
    }

    /** Ensure one default series exists per Transaction Master type for the FY. */
    public function seedSeries(FinancialYear $fy): int
    {
        $added = 0;
        foreach (\App\Support\TransactionTypes::all() as $type => $meta) {
            $exists = DocumentSeries::where('doc_type', $type)->where('financial_year_id', $fy->id)->exists();
            if ($exists) {
                continue;
            }
            DocumentSeries::create([
                'doc_type' => $type,
                'financial_year_id' => $fy->id,
                'name' => $meta['label'],
                'prefix' => $meta['prefix'],
                'next_number' => 1,
                'padding' => 4,
                'is_default' => true,
                'is_active' => true,
            ]);
            $added++;
        }

        return $added;
    }

    /** Abort unless $date falls inside the active (current) financial year. */
    public function assertDateInCurrent(string $date, string $field = 'date'): void
    {
        $fy = FinancialYear::current();
        $d = Carbon::parse($date)->toDateString();
        if ($d < $fy->start_date->toDateString() || $d > $fy->end_date->toDateString()) {
            throw ValidationException::withMessages([
                $field => __('Date must fall in the active financial year (:from to :to).', [
                    'from' => $fy->start_date->format('d-m-Y'),
                    'to' => $fy->end_date->format('d-m-Y'),
                ]),
            ]);
        }
    }

    /** Default voucher date: today if inside current FY, else FY start. */
    public function defaultVoucherDate(?Carbon $today = null): string
    {
        $today = $today ?: now();
        $fy = FinancialYear::current();
        if ($today->betweenIncluded($fy->start_date, $fy->end_date)) {
            return $today->toDateString();
        }

        return $fy->start_date->toDateString();
    }

    private function assertNoOverlap(string $start, string $end, ?int $ignoreId = null): void
    {
        if (Carbon::parse($end)->lt(Carbon::parse($start))) {
            throw ValidationException::withMessages(['end_date' => __('End date must be on or after start date.')]);
        }

        $q = FinancialYear::query()
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start);
        if ($ignoreId) {
            $q->where('id', '!=', $ignoreId);
        }
        if ($q->exists()) {
            throw ValidationException::withMessages(['start_date' => __('This range overlaps another financial year.')]);
        }
    }
}
