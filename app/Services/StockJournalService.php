<?php

namespace App\Services;

use App\Models\FinancialYear;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockJournal;
use App\Support\StockQty;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stock-only vouchers: journal / rejection / issue / physical / return / transfer.
 * Qty books = stock_movements; header is audit + number series only.
 * Transfer posts OUT at from-place and IN at to-place (same table, double entry).
 */
class StockJournalService
{
    /** UI type => document series code + movement_type */
    public const TYPES = [
        'journal' => ['doc' => 'stock_journal', 'label' => 'Stock Journal'],
        'rejection' => ['doc' => 'rejection', 'label' => 'Stock Rejection'],
        'issue' => ['doc' => 'stock_issue', 'label' => 'Stock Issue'],
        'physical' => ['doc' => 'physical_stock', 'label' => 'Physical Stock'],
        'return' => ['doc' => 'stock_return', 'label' => 'Stock Return'],
        'transfer' => ['doc' => 'stock_transfer', 'label' => 'Stock Transfer'],
    ];

    public function __construct(
        private StockService $stock,
        private InvoiceNumberGenerator $numbers,
    ) {}

    public function create(array $data, ?int $userId = null): StockJournal
    {
        $type = $data['type'] ?? 'journal';
        if (! isset(self::TYPES[$type])) {
            throw ValidationException::withMessages(['type' => __('Unknown stock journal type.')]);
        }

        return DB::transaction(function () use ($data, $type, $userId) {
            $placeId = (int) $data['godown_id'];
            $toPlaceId = filled($data['to_godown_id'] ?? null) ? (int) $data['to_godown_id'] : null;
            $date = $data['journal_date'];
            $doc = self::TYPES[$type]['doc'];

            if ($type === 'transfer') {
                if (! $toPlaceId) {
                    throw ValidationException::withMessages(['to_godown_id' => __('Select the destination place.')]);
                }
                if ($toPlaceId === $placeId) {
                    throw ValidationException::withMessages(['to_godown_id' => __('From and to place must be different.')]);
                }
            }

            $journal = StockJournal::create([
                'journal_no' => $this->numbers->next(
                    $doc,
                    $date,
                    isset($data['document_series_id']) ? (int) $data['document_series_id'] : null
                ),
                'financial_year_id' => FinancialYear::forDate($date)->id,
                'type' => $type,
                'journal_date' => $date,
                'godown_id' => $placeId,
                'to_godown_id' => $type === 'transfer' ? $toPlaceId : null,
                'notes' => $data['notes'] ?? null,
                'status' => 'active',
                'user_id' => $userId,
            ]);

            $this->postItems($journal, $type, $placeId, $toPlaceId, $data['items'] ?? []);

            return $journal->fresh(['lines.product', 'godown', 'toGodown']);
        });
    }

    /**
     * Posted edit: reverse log rows, replace lines, re-post (same voucher number).
     */
    public function update(StockJournal $journal, array $data, ?int $userId = null): StockJournal
    {
        return DB::transaction(function () use ($journal, $data, $userId) {
            $journal = StockJournal::lockForUpdate()->findOrFail($journal->id);
            if ($journal->isCancelled()) {
                throw ValidationException::withMessages(['journal' => __('Cancelled voucher cannot be edited.')]);
            }

            $type = $journal->type;
            $placeId = (int) $data['godown_id'];
            $toPlaceId = filled($data['to_godown_id'] ?? null) ? (int) $data['to_godown_id'] : null;
            $date = $data['journal_date'];

            if ($type === 'transfer') {
                if (! $toPlaceId) {
                    throw ValidationException::withMessages(['to_godown_id' => __('Select the destination place.')]);
                }
                if ($toPlaceId === $placeId) {
                    throw ValidationException::withMessages(['to_godown_id' => __('From and to place must be different.')]);
                }
            }

            $this->stock->reverse('stock_journal', $journal->id, $date);
            $journal->lines()->delete();

            $journal->update([
                'journal_date' => $date,
                'financial_year_id' => FinancialYear::forDate($date)->id,
                'godown_id' => $placeId,
                'to_godown_id' => $type === 'transfer' ? $toPlaceId : null,
                'notes' => $data['notes'] ?? null,
                'user_id' => $userId ?? $journal->user_id,
            ]);

            $this->postItems($journal->fresh(), $type, $placeId, $toPlaceId, $data['items'] ?? []);

            return $journal->fresh(['lines.product', 'godown', 'toGodown']);
        });
    }

    public function cancel(StockJournal $journal): void
    {
        DB::transaction(function () use ($journal) {
            $journal = StockJournal::lockForUpdate()->findOrFail($journal->id);
            if ($journal->isCancelled()) {
                return;
            }
            $this->stock->reverse('stock_journal', $journal->id, $journal->journal_date);
            $journal->update(['status' => 'cancelled']);
        });
    }

    /** @param  array<int, array<string, mixed>>  $lines */
    private function postItems(StockJournal $journal, string $type, int $placeId, ?int $toPlaceId, array $lines): void
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['items' => __('Add at least one line.')]);
        }

        foreach ($lines as $row) {
            if ($type === 'transfer') {
                $this->postTransfer($journal, $placeId, (int) $toPlaceId, $row);
            } else {
                $this->postLine($journal, $type, $placeId, $row);
            }
        }

        if (! $journal->lines()->exists()) {
            throw ValidationException::withMessages(['items' => __('Nothing to post — check quantities.')]);
        }
    }

    private function postTransfer(StockJournal $journal, int $fromPlace, int $toPlace, array $row): void
    {
        $product = Product::findOrFail($row['product_id']);
        $qty = round((float) ($row['qty'] ?? 0), 3);
        if ($qty <= 0.0005) {
            return;
        }

        $batchId = filled($row['batch_id'] ?? null) ? (int) $row['batch_id'] : null;
        $alloc = $this->stock->issue(
            $product->id,
            $fromPlace,
            $qty,
            $batchId,
            'stock_transfer',
            'stock_journal',
            $journal->id,
            $journal->journal_date,
            $journal->journal_no,
        );

        foreach ($alloc as $a) {
            $src = ProductBatch::findOrFail($a['batch_id']);
            $journal->lines()->create([
                'side' => 'out',
                'product_id' => $product->id,
                'batch_id' => $src->id,
                'batch_no' => $src->batch_no,
                'godown_id' => $fromPlace,
                'qty' => $a['qty'],
                'rate' => $a['rate'],
                'narration' => __('Transfer out'),
            ]);

            $dest = $this->stock->receive([
                'product_id' => $product->id,
                'godown_id' => $toPlace,
                'batch_no' => $src->batch_no === StockService::DEFAULT_BATCH ? null : $src->batch_no,
                'mfg_date' => $src->mfg_date?->toDateString(),
                'expiry_date' => $src->expiry_date?->toDateString(),
                'qty' => $a['qty'],
                'rate' => $a['rate'],
                'mrp' => (float) $src->mrp,
                'type' => 'stock_transfer',
                'ref_type' => 'stock_journal',
                'ref_id' => $journal->id,
                'date' => $journal->journal_date,
                'narration' => $journal->journal_no,
            ]);

            $journal->lines()->create([
                'side' => 'in',
                'product_id' => $product->id,
                'batch_id' => $dest->id,
                'batch_no' => $dest->batch_no,
                'godown_id' => $toPlace,
                'qty' => $a['qty'],
                'rate' => $a['rate'],
                'narration' => __('Transfer in'),
            ]);
        }
    }

    private function postLine(StockJournal $journal, string $type, int $headerPlace, array $row): void
    {
        $product = Product::findOrFail($row['product_id']);
        $placeId = (int) ($row['godown_id'] ?? $headerPlace);
        $batchId = filled($row['batch_id'] ?? null) ? (int) $row['batch_id'] : null;
        $rate = (float) ($row['rate'] ?? 0);

        if ($type === 'physical') {
            $this->postPhysical($journal, $product, $placeId, $batchId, $row, $rate);

            return;
        }

        $qty = round((float) ($row['qty'] ?? 0), 3);
        if ($qty <= 0.0005) {
            return;
        }

        $side = match ($type) {
            'journal' => in_array($row['side'] ?? 'out', ['in', 'out'], true) ? $row['side'] : 'out',
            'return' => 'in',
            default => 'out', // rejection, issue
        };

        if ($side === 'in') {
            $batch = $this->stock->receive([
                'product_id' => $product->id,
                'godown_id' => $placeId,
                'batch_no' => $row['batch_no'] ?? null,
                'mfg_date' => $row['mfg_date'] ?? null,
                'expiry_date' => $row['expiry_date'] ?? null,
                'qty' => $qty,
                'rate' => $rate > 0 ? $rate : null,
                'type' => self::TYPES[$type]['doc'],
                'ref_type' => 'stock_journal',
                'ref_id' => $journal->id,
                'date' => $journal->journal_date,
                'narration' => $journal->journal_no,
            ]);
            $journal->lines()->create([
                'side' => 'in',
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'batch_no' => $batch->batch_no,
                'godown_id' => $placeId,
                'qty' => $qty,
                'rate' => $rate ?: (float) $batch->purchase_rate,
            ]);

            return;
        }

        $alloc = $this->stock->issue(
            $product->id,
            $placeId,
            $qty,
            $batchId,
            self::TYPES[$type]['doc'],
            'stock_journal',
            $journal->id,
            $journal->journal_date,
            $journal->journal_no,
        );
        foreach ($alloc as $a) {
            $journal->lines()->create([
                'side' => 'out',
                'product_id' => $product->id,
                'batch_id' => $a['batch_id'],
                'batch_no' => ProductBatch::find($a['batch_id'])?->batch_no,
                'godown_id' => $placeId,
                'qty' => $a['qty'],
                'rate' => $a['rate'],
            ]);
        }
    }

    private function postPhysical(StockJournal $journal, Product $product, int $placeId, ?int $batchId, array $row, float $rate): void
    {
        if ($product->batch_required && ! $batchId) {
            throw ValidationException::withMessages([
                'items' => __('Select a batch for :product.', ['product' => $product->display_name]),
            ]);
        }

        $counted = round((float) ($row['counted_qty'] ?? $row['qty'] ?? 0), 3);
        if ($counted < 0) {
            throw ValidationException::withMessages(['items' => __('Counted qty cannot be negative.')]);
        }

        if ($batchId) {
            $system = StockQty::freshBatchOnHand($batchId);
            $diff = round($counted - $system, 3);
            if (abs($diff) <= 0.0005) {
                $journal->lines()->create([
                    'side' => 'count',
                    'product_id' => $product->id,
                    'batch_id' => $batchId,
                    'godown_id' => $placeId,
                    'qty' => 0,
                    'counted_qty' => $counted,
                    'system_qty' => $system,
                    'rate' => $rate,
                ]);

                return;
            }
            if ($diff > 0) {
                $batch = ProductBatch::findOrFail($batchId);
                $this->stock->returnToBatch(
                    $batchId,
                    $diff,
                    'physical_stock',
                    'stock_journal',
                    $journal->id,
                    $journal->journal_date,
                    $journal->journal_no,
                );
                $journal->lines()->create([
                    'side' => 'in',
                    'product_id' => $product->id,
                    'batch_id' => $batchId,
                    'batch_no' => $batch->batch_no,
                    'godown_id' => $placeId,
                    'qty' => $diff,
                    'counted_qty' => $counted,
                    'system_qty' => $system,
                    'rate' => $rate ?: (float) $batch->purchase_rate,
                ]);
            } else {
                $alloc = $this->stock->issue(
                    $product->id, $placeId, abs($diff), $batchId,
                    'physical_stock', 'stock_journal', $journal->id, $journal->journal_date, $journal->journal_no,
                );
                $journal->lines()->create([
                    'side' => 'out',
                    'product_id' => $product->id,
                    'batch_id' => $alloc[0]['batch_id'],
                    'godown_id' => $placeId,
                    'qty' => abs($diff),
                    'counted_qty' => $counted,
                    'system_qty' => $system,
                    'rate' => $alloc[0]['rate'],
                ]);
            }

            return;
        }

        $system = StockQty::onHand($product->id, $placeId);
        $diff = round($counted - $system, 3);
        if (abs($diff) <= 0.0005) {
            $journal->lines()->create([
                'side' => 'count', 'product_id' => $product->id, 'godown_id' => $placeId,
                'qty' => 0, 'counted_qty' => $counted, 'system_qty' => $system, 'rate' => $rate,
            ]);

            return;
        }
        if ($diff > 0) {
            $batch = $this->stock->receive([
                'product_id' => $product->id, 'godown_id' => $placeId, 'qty' => $diff,
                'rate' => $rate > 0 ? $rate : null, 'type' => 'physical_stock',
                'ref_type' => 'stock_journal', 'ref_id' => $journal->id,
                'date' => $journal->journal_date, 'narration' => $journal->journal_no,
            ]);
            $journal->lines()->create([
                'side' => 'in', 'product_id' => $product->id, 'batch_id' => $batch->id,
                'godown_id' => $placeId, 'qty' => $diff, 'counted_qty' => $counted,
                'system_qty' => $system, 'rate' => $rate ?: (float) $batch->purchase_rate,
            ]);
        } else {
            $alloc = $this->stock->issue(
                $product->id, $placeId, abs($diff), null,
                'physical_stock', 'stock_journal', $journal->id, $journal->journal_date, $journal->journal_no,
            );
            $journal->lines()->create([
                'side' => 'out', 'product_id' => $product->id, 'batch_id' => $alloc[0]['batch_id'],
                'godown_id' => $placeId, 'qty' => abs($diff), 'counted_qty' => $counted,
                'system_qty' => $system, 'rate' => $alloc[0]['rate'],
            ]);
        }
    }
}
