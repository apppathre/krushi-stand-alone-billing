<?php

namespace App\Support;

use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * On-hand qty from the single stock log (`stock_movements`) only.
 * product_batches.qty_on_hand is a synced cache for list filters — never the books of record.
 */
class StockQty
{
    /** @var array<string, array<int, float>> */
    private static array $batchCache = [];

    /** @var array<string, array<int, float>> */
    private static array $productCache = [];

    /** Qty for one batch bucket: SUM(qty_in − qty_out). */
    public static function batchOnHand(int $batchId, ?string $asOf = null): float
    {
        $map = self::batchBalances(null, null, $asOf);

        return (float) ($map[$batchId] ?? 0);
    }

    /** Qty for one product (optionally one godown). */
    public static function onHand(int $productId, ?int $godownId = null, ?string $asOf = null): float
    {
        $map = self::productBalances($godownId, $asOf);

        return (float) ($map[$productId] ?? 0);
    }

    /**
     * batch_id => balance. One GROUP BY pass; request-memoized.
     *
     * @param  int|array<int, int>|null  $godownId
     * @return array<int, float>
     */
    public static function batchBalances(int|array|null $godownId = null, ?int $productId = null, ?string $asOf = null): array
    {
        $gKey = is_array($godownId) ? implode(',', $godownId) : (string) ($godownId ?? '');
        $cacheKey = 'b|'.$gKey.'|'.($productId ?? '').'|'.($asOf ?? '');
        if (isset(self::$batchCache[$cacheKey])) {
            return self::$batchCache[$cacheKey];
        }

        $rows = StockMovement::query()
            ->whereNotNull('batch_id')
            ->when($godownId !== null && $godownId !== [], function ($q) use ($godownId) {
                is_array($godownId) ? $q->whereIn('godown_id', $godownId) : $q->where('godown_id', $godownId);
            })
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->when($asOf, fn ($q) => $q->whereDate('movement_date', '<=', $asOf))
            ->select('batch_id')
            ->selectRaw('SUM(qty_in - qty_out) as bal')
            ->groupBy('batch_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->batch_id] = round((float) $row->bal, 3);
        }

        return self::$batchCache[$cacheKey] = $out;
    }

    /**
     * product_id => balance. One GROUP BY pass; request-memoized.
     *
     * @param  int|array<int, int>|null  $godownId
     * @return array<int, float>
     */
    public static function productBalances(int|array|null $godownId = null, ?string $asOf = null): array
    {
        $gKey = is_array($godownId) ? implode(',', $godownId) : (string) ($godownId ?? '');
        $cacheKey = 'p|'.$gKey.'|'.($asOf ?? '');
        if (isset(self::$productCache[$cacheKey])) {
            return self::$productCache[$cacheKey];
        }

        $rows = StockMovement::query()
            ->when($godownId !== null && $godownId !== [], function ($q) use ($godownId) {
                is_array($godownId) ? $q->whereIn('godown_id', $godownId) : $q->where('godown_id', $godownId);
            })
            ->when($asOf, fn ($q) => $q->whereDate('movement_date', '<=', $asOf))
            ->select('product_id')
            ->selectRaw('SUM(qty_in - qty_out) as bal')
            ->groupBy('product_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->product_id] = round((float) $row->bal, 3);
        }

        return self::$productCache[$cacheKey] = $out;
    }

    /**
     * Correlated subquery SQL for product on-hand from the move log.
     * Bind godown with a placeholder when $godownPlaceholder is true.
     */
    public static function productOnHandSql(?int $godownId = null, bool $godownPlaceholder = false): string
    {
        $sql = '(SELECT COALESCE(SUM(sm.qty_in - sm.qty_out), 0) FROM stock_movements sm WHERE sm.product_id = products.id';
        if ($godownPlaceholder) {
            $sql .= ' AND sm.godown_id = ?';
        } elseif ($godownId !== null) {
            $sql .= ' AND sm.godown_id = '.(int) $godownId;
        }
        $sql .= ')';

        return $sql;
    }

    /** IN-list godown filter for dashboard-style queries. */
    public static function productOnHandSqlForGodowns(array $godownIds): string
    {
        $inList = implode(',', array_map('intval', $godownIds));
        if ($inList === '') {
            return '0';
        }

        return "(SELECT COALESCE(SUM(sm.qty_in - sm.qty_out), 0) FROM stock_movements sm WHERE sm.product_id = products.id AND sm.godown_id IN ($inList))";
    }

    /** Movement-rate stock value for one product (optional single godown). */
    public static function productValueSql(?int $godownId = null): string
    {
        $sql = '(SELECT COALESCE(SUM((sm.qty_in - sm.qty_out) * sm.rate), 0) FROM stock_movements sm WHERE sm.product_id = products.id';
        if ($godownId !== null) {
            $sql .= ' AND sm.godown_id = '.(int) $godownId;
        }
        $sql .= ')';

        return $sql;
    }

    /** Movement-rate stock value limited to accessible godowns. */
    public static function productValueSqlForGodowns(array $godownIds): string
    {
        $inList = implode(',', array_map('intval', $godownIds));
        if ($inList === '') {
            return '0';
        }

        return "(SELECT COALESCE(SUM((sm.qty_in - sm.qty_out) * sm.rate), 0) FROM stock_movements sm WHERE sm.product_id = products.id AND sm.godown_id IN ($inList))";
    }

    public static function flush(): void
    {
        self::$batchCache = [];
        self::$productCache = [];
    }

    /** Fresh SUM for one batch (bypasses memo — use after posting). */
    public static function freshBatchOnHand(int $batchId): float
    {
        return round((float) StockMovement::where('batch_id', $batchId)
            ->value(DB::raw('COALESCE(SUM(qty_in - qty_out), 0)')), 3);
    }
}
