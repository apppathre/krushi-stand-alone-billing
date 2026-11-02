<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Support\StockQty;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stock books of record = stock_movements (single table).
 * product_batches holds batch identity + a synced qty_on_hand cache for list filters.
 */
class StockService
{
    public const DEFAULT_BATCH = 'NA';

    /**
     * Add stock into a (product, godown, batch) bucket and log the movement.
     *
     * @param  array{product_id:int, godown_id:int, batch_no?:?string, mfg_date?:?string, expiry_date?:?string, qty:float, rate?:float, mrp?:float, type:string, ref_type?:?string, ref_id?:?int, date:mixed, narration?:?string}  $d
     */
    public function receive(array $d): ProductBatch
    {
        $product = Product::findOrFail($d['product_id']);
        $batchNo = $product->batch_required && filled($d['batch_no'] ?? null)
            ? trim($d['batch_no'])
            : self::DEFAULT_BATCH;

        $batch = ProductBatch::where('product_id', $product->id)
            ->where('godown_id', $d['godown_id'])
            ->where('batch_no', $batchNo)
            ->lockForUpdate()
            ->first();

        if (! $batch) {
            $batch = ProductBatch::create([
                'product_id' => $product->id,
                'godown_id' => $d['godown_id'],
                'rack_id' => $d['rack_id'] ?? null,
                'batch_no' => $batchNo,
                'mfg_date' => $d['mfg_date'] ?? null,
                'expiry_date' => $d['expiry_date'] ?? null,
                'purchase_rate' => $d['rate'] ?? 0,
                'mrp' => $d['mrp'] ?? 0,
                'qty_on_hand' => 0,
            ]);
            $batch = ProductBatch::lockForUpdate()->findOrFail($batch->id);
        } else {
            $batch->fill(array_filter([
                'mfg_date' => $d['mfg_date'] ?? null,
                'expiry_date' => $d['expiry_date'] ?? null,
                'purchase_rate' => $d['rate'] ?? null,
                'mrp' => $d['mrp'] ?? null,
            ], fn ($v) => filled($v) && $v !== 0 && $v !== 0.0));
            // Optional put-away on receive: only set when provided (does not clear an existing rack).
            if (array_key_exists('rack_id', $d) && filled($d['rack_id'])) {
                $batch->rack_id = (int) $d['rack_id'];
            }
            $batch->save();
        }

        $this->log($batch, 'in', (float) $d['qty'], (float) ($d['rate'] ?? $batch->purchase_rate), $d);
        $this->syncBatchCache($batch);

        return $batch;
    }

    /**
     * Take stock out. With $batchId it uses that batch; otherwise allocates FIFO (earliest expiry first).
     *
     * @return array<int, array{batch_id:int, qty:float, rate:float}>
     */
    public function issue(int $productId, int $godownId, float $qty, ?int $batchId, string $type, ?string $refType, ?int $refId, mixed $date, ?string $narration = null): array
    {
        $product = Product::findOrFail($productId);

        if ($batchId) {
            $batches = ProductBatch::where('id', $batchId)
                ->where('product_id', $productId)->where('godown_id', $godownId)
                ->lockForUpdate()->get();
        } else {
            if ($product->batch_required) {
                throw ValidationException::withMessages([
                    'items' => __('Select a batch for :product.', ['product' => $product->display_name]),
                ]);
            }
            // Lock candidate batches; available qty comes from the move log (not the cache alone).
            $batches = ProductBatch::where('product_id', $productId)->where('godown_id', $godownId)
                ->orderByRaw('expiry_date IS NULL, expiry_date ASC')->orderBy('id')
                ->lockForUpdate()->get();
        }

        $balances = [];
        $available = 0.0;
        foreach ($batches as $batch) {
            $bal = StockQty::freshBatchOnHand($batch->id);
            $balances[$batch->id] = $bal;
            if ($bal > 0.0005) {
                $available = round($available + $bal, 3);
            }
        }

        if ($batches->isEmpty() || $available + 0.0005 < $qty) {
            throw ValidationException::withMessages([
                'items' => __('Insufficient stock for :product. Available: :qty', [
                    'product' => $product->display_name,
                    'qty' => qty($available),
                ]),
            ]);
        }

        $remaining = $qty;
        $allocations = [];
        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }
            $have = (float) ($balances[$batch->id] ?? 0);
            if ($have <= 0.0005) {
                continue;
            }
            $take = min($remaining, $have);
            $remaining = round($remaining - $take, 3);

            $this->log($batch, 'out', $take, (float) $batch->purchase_rate, [
                'type' => $type, 'ref_type' => $refType, 'ref_id' => $refId, 'date' => $date, 'narration' => $narration,
            ]);
            $this->syncBatchCache($batch);
            $allocations[] = ['batch_id' => $batch->id, 'qty' => $take, 'rate' => (float) $batch->purchase_rate];
        }

        return $allocations;
    }

    /** Put stock back into a specific batch (e.g. sale return). */
    public function returnToBatch(int $batchId, float $qty, string $type, ?string $refType, ?int $refId, mixed $date, ?string $narration = null): void
    {
        $batch = ProductBatch::lockForUpdate()->findOrFail($batchId);

        $this->log($batch, 'in', $qty, (float) $batch->purchase_rate, [
            'type' => $type, 'ref_type' => $refType, 'ref_id' => $refId, 'date' => $date, 'narration' => $narration,
        ]);
        $this->syncBatchCache($batch);
    }

    /**
     * Credit-note stock in: restore qty onto the batches this sale issued from.
     * When the sale split FIFO across batches, returns fill those buckets in issue order
     * (not only the first batch_id stored on the sale line).
     */
    public function returnSaleQty(
        int $saleId,
        int $productId,
        int $godownId,
        float $qty,
        ?int $preferredBatchId,
        float $fallbackRate,
        string $type,
        int $returnId,
        mixed $date,
        ?string $narration = null,
    ): void {
        $issued = StockMovement::query()
            ->where('ref_type', 'sale')
            ->where('ref_id', $saleId)
            ->where('product_id', $productId)
            ->where('godown_id', $godownId)
            ->whereNotNull('batch_id')
            ->select('batch_id')
            ->selectRaw('SUM(qty_out - qty_in) as net_out')
            ->selectRaw('MIN(id) as first_id')
            ->groupBy('batch_id')
            ->havingRaw('SUM(qty_out - qty_in) > 0.0005')
            ->orderBy('first_id')
            ->get();

        // Qty already put back by earlier credit notes on this sale (same product/place).
        $priorReturnIds = DB::table('sale_returns')->where('sale_id', $saleId)->pluck('id');
        $alreadyBack = [];
        if ($priorReturnIds->isNotEmpty()) {
            $alreadyBack = StockMovement::query()
                ->where('ref_type', 'sale_return')
                ->whereIn('ref_id', $priorReturnIds)
                ->where('product_id', $productId)
                ->where('godown_id', $godownId)
                ->whereNotNull('batch_id')
                ->select('batch_id')
                ->selectRaw('SUM(qty_in - qty_out) as back')
                ->groupBy('batch_id')
                ->pluck('back', 'batch_id')
                ->map(fn ($v) => (float) $v)
                ->all();
        }

        $remaining = round($qty, 3);

        // Prefer the line's batch first when it still has issued capacity.
        if ($preferredBatchId && $issued->isNotEmpty()) {
            $issued = $issued->sortBy(fn ($r) => (int) $r->batch_id === $preferredBatchId ? 0 : 1)->values();
        }

        foreach ($issued as $row) {
            if ($remaining <= 0.0005) {
                break;
            }
            $batchId = (int) $row->batch_id;
            $cap = round((float) $row->net_out - (float) ($alreadyBack[$batchId] ?? 0), 3);
            if ($cap <= 0.0005) {
                continue;
            }
            $take = min($remaining, $cap);
            $this->returnToBatch($batchId, $take, $type, 'sale_return', $returnId, $date, $narration);
            $alreadyBack[$batchId] = (float) ($alreadyBack[$batchId] ?? 0) + $take;
            $remaining = round($remaining - $take, 3);
        }

        if ($remaining > 0.0005) {
            // Fallback: preferred batch or default receive bucket.
            if ($preferredBatchId) {
                $this->returnToBatch($preferredBatchId, $remaining, $type, 'sale_return', $returnId, $date, $narration);
            } else {
                $this->receive([
                    'product_id' => $productId,
                    'godown_id' => $godownId,
                    'qty' => $remaining,
                    'rate' => $fallbackRate,
                    'type' => $type,
                    'ref_type' => 'sale_return',
                    'ref_id' => $returnId,
                    'date' => $date,
                    'narration' => $narration,
                ]);
            }
        }
    }

    /**
     * Undo a document's net stock effect (cancel / edit-before-repost).
     * Nets ALL rows for the ref (including prior reversals), so a second edit
     * does not double-reverse already-cancelled original lines.
     */
    public function reverse(string $refType, int $refId, mixed $date = null): void
    {
        $nets = StockMovement::query()
            ->where('ref_type', $refType)
            ->where('ref_id', $refId)
            ->select('product_id', 'godown_id', 'batch_id')
            ->selectRaw('SUM(qty_in - qty_out) as net_qty')
            ->selectRaw('CASE WHEN ABS(SUM(qty_in - qty_out)) > 0.0005 THEN SUM((qty_in - qty_out) * rate) / SUM(qty_in - qty_out) ELSE 0 END as avg_rate')
            ->groupBy('product_id', 'godown_id', 'batch_id')
            ->havingRaw('ABS(SUM(qty_in - qty_out)) > 0.0005')
            ->get();

        foreach ($nets as $row) {
            $net = round((float) $row->net_qty, 3); // +in / −out still on the books for this doc
            if (abs($net) <= 0.0005) {
                continue;
            }

            $batch = $row->batch_id ? ProductBatch::lockForUpdate()->find($row->batch_id) : null;
            if ($batch) {
                $have = StockQty::freshBatchOnHand($batch->id);
                // Removing a net receipt (net > 0) must leave enough on hand.
                if ($net > 0.0005 && round($have - $net, 3) < -0.0005) {
                    throw ValidationException::withMessages([
                        'cancel' => __('Cannot cancel: stock of batch :batch has already been used.', ['batch' => $batch->batch_no]),
                    ]);
                }
            }

            StockMovement::create([
                'movement_date' => $date ?? now()->toDateString(),
                'product_id' => $row->product_id,
                'godown_id' => $row->godown_id,
                'batch_id' => $row->batch_id,
                'movement_type' => 'reversal',
                'qty_in' => $net < 0 ? abs($net) : 0,
                'qty_out' => $net > 0 ? $net : 0,
                'rate' => round((float) $row->avg_rate, 2),
                'ref_type' => $refType,
                'ref_id' => $refId,
                'narration' => __('Cancelled'),
                'user_id' => Auth::id(),
            ]);

            if ($batch) {
                $this->syncBatchCache($batch);
            }
        }
    }

    /** Total quantity on hand for a product (optionally in one godown) — from the move log. */
    public function onHand(int $productId, ?int $godownId = null): float
    {
        return StockQty::onHand($productId, $godownId);
    }

    /** Stock valuation at cost as of a date, from the movement log. */
    public function valueAsOf(?string $date, ?array $godownIds = null): float
    {
        return (float) StockMovement::query()
            ->when($date, fn ($q) => $q->whereDate('movement_date', '<=', $date))
            ->when($godownIds, fn ($q) => $q->whereIn('godown_id', $godownIds))
            ->value(DB::raw('COALESCE(SUM((qty_in - qty_out) * rate), 0)'));
    }

    /** Sync product_batches.qty_on_hand cache from SUM(stock_movements). */
    public function syncBatchCache(ProductBatch $batch): void
    {
        StockQty::flush();
        $batch->qty_on_hand = StockQty::freshBatchOnHand($batch->id);
        $batch->save();
    }

    private function log(ProductBatch $batch, string $dir, float $qty, float $rate, array $d): void
    {
        StockMovement::create([
            'movement_date' => $d['date'],
            'product_id' => $batch->product_id,
            'godown_id' => $batch->godown_id,
            'batch_id' => $batch->id,
            'movement_type' => $d['type'],
            'qty_in' => $dir === 'in' ? $qty : 0,
            'qty_out' => $dir === 'out' ? $qty : 0,
            'rate' => $rate,
            'ref_type' => $d['ref_type'] ?? null,
            'ref_id' => $d['ref_id'] ?? null,
            'narration' => $d['narration'] ?? null,
            'user_id' => Auth::id(),
        ]);
        StockQty::flush();
    }
}
