<?php

namespace App\Services;

use App\Models\Godown;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Rack;
use App\Support\StockQty;

/** Builds the JSON product lists consumed by the invoice / purchase / opening-stock forms. */
class ProductCatalog
{
    /**
     * Products with in-stock batches across one or more places (sales).
     * Qty from stock_movements. Each batch carries godown_id (+ rack label) so lines can pick place.
     *
     * @param  int|array<int, int>  $godownIds
     */
    public function forSale(int|array $godownIds): array
    {
        $godownIds = array_values(array_unique(array_filter(array_map('intval', (array) $godownIds))));
        if ($godownIds === []) {
            return [];
        }

        $balances = StockQty::batchBalances($godownIds);
        $inStockIds = array_keys(array_filter($balances, fn ($q) => $q > 0.0005));
        $placeNames = Godown::query()->whereIn('id', $godownIds)->get()
            ->mapWithKeys(fn (Godown $g) => [$g->id => $g->display_name])->all();

        $batches = $inStockIds === []
            ? collect()
            : ProductBatch::with('rack:id,code,name,aisle,godown_id')
                ->whereIn('godown_id', $godownIds)
                ->whereIn('id', $inStockIds)
                ->orderByRaw('expiry_date IS NULL, expiry_date ASC')
                ->orderBy('id')
                ->get(['id', 'product_id', 'godown_id', 'rack_id', 'batch_no', 'mfg_date', 'expiry_date', 'mrp'])
                ->groupBy('product_id');

        return Product::with('unit:id,short_name')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'name_mr', 'code', 'hsn_code', 'gst_rate', 'sale_rate', 'mrp', 'batch_required', 'unit_id'])
            ->map(function (Product $p) use ($batches, $balances, $placeNames) {
                $pb = $batches->get($p->id, collect());
                $mapped = $pb->map(fn ($b) => [
                    'id' => $b->id,
                    'godown_id' => (int) $b->godown_id,
                    'place' => $placeNames[(int) $b->godown_id] ?? ('#'.$b->godown_id),
                    'batch_no' => $b->batch_no,
                    'mfg' => $b->mfg_date?->format('d-m-Y'),
                    'expiry' => $b->expiry_date?->format('d-m-Y'),
                    'qty' => (float) ($balances[$b->id] ?? 0),
                    'mrp' => (float) $b->mrp,
                    'rack' => $b->rack?->label,
                ])->filter(fn ($b) => $b['qty'] > 0.0005)->values()->all();

                $byPlace = [];
                foreach ($mapped as $b) {
                    $gid = (int) $b['godown_id'];
                    if (! isset($byPlace[$gid])) {
                        $byPlace[$gid] = [
                            'godown_id' => $gid,
                            'name' => $b['place'],
                            'qty' => 0.0,
                        ];
                    }
                    $byPlace[$gid]['qty'] = round($byPlace[$gid]['qty'] + (float) $b['qty'], 3);
                }
                $stockByPlace = array_values($byPlace);
                usort($stockByPlace, fn ($a, $b) => $b['qty'] <=> $a['qty']);

                return $this->base($p, (float) $p->sale_rate) + [
                    'stock' => round(array_sum(array_column($mapped, 'qty')), 3),
                    'stock_by_place' => $stockByPlace,
                    'batches' => $mapped,
                ];
            })->values()->all();
    }

    /** Products for purchase / opening stock entry. */
    public function forPurchase(): array
    {
        return Product::with('unit:id,short_name')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'name_mr', 'code', 'hsn_code', 'gst_rate', 'purchase_rate', 'mrp', 'batch_required', 'unit_id'])
            ->map(fn (Product $p) => $this->base($p, (float) $p->purchase_rate))->values()->all();
    }

    /**
     * Active racks grouped by godown for purchase put-away (optional on receive).
     *
     * @param  array<int, int>  $godownIds
     * @return array<int, array<int, array{id:int, label:string}>>
     */
    public function racksByPlace(array $godownIds): array
    {
        $godownIds = array_values(array_unique(array_filter(array_map('intval', $godownIds))));
        if ($godownIds === []) {
            return [];
        }

        $defaults = Godown::query()->whereIn('id', $godownIds)->pluck('default_rack_id', 'id')->all();
        $out = [];
        Rack::query()
            ->whereIn('godown_id', $godownIds)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('code')
            ->get()
            ->each(function (Rack $r) use (&$out, $defaults) {
                $gid = (int) $r->godown_id;
                $out[$gid][] = [
                    'id' => $r->id,
                    'label' => $r->label,
                    'is_default' => (bool) $r->is_default || (int) ($defaults[$gid] ?? 0) === (int) $r->id,
                ];
            });

        return $out;
    }

    /**
     * Default rack id per place (godown default_rack_id, else rack.is_default, else first).
     *
     * @param  array<int, int>  $godownIds
     * @return array<int, int|null>
     */
    public function defaultRackByPlace(array $godownIds): array
    {
        $grouped = $this->racksByPlace($godownIds);
        $out = [];
        foreach ($grouped as $gid => $racks) {
            $pick = collect($racks)->firstWhere('is_default', true) ?? ($racks[0] ?? null);
            $out[(int) $gid] = $pick['id'] ?? null;
        }

        return $out;
    }

    private function base(Product $p, float $rate): array
    {
        return [
            'id' => $p->id,
            'name' => $p->display_name,
            'code' => $p->code,
            'hsn' => $p->hsn_code,
            'gst' => (float) $p->gst_rate,
            'rate' => $rate,
            'mrp' => (float) $p->mrp,
            'batch_required' => (bool) $p->batch_required,
            'unit' => $p->unit?->short_name,
        ];
    }
}
