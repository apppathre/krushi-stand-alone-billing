<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Collection;

/**
 * GST exception / audit list — bills & lines that need attention before filing.
 */
class GstExceptionService
{
    /**
     * @param  list<int>|null  $godownIds
     * @return array{rows: Collection, summary: array<string,int>, from: string, to: string}
     */
    public function build(string $from, string $to, ?array $godownIds = null, ?string $type = null, string $doc = 'all'): array
    {
        $rows = collect();

        if ($doc === 'all' || $doc === 'sale') {
            $rows = $rows->merge($this->saleExceptions($from, $to, $godownIds));
        }
        if ($doc === 'all' || $doc === 'purchase') {
            $rows = $rows->merge($this->purchaseExceptions($from, $to, $godownIds));
        }

        if (filled($type) && $type !== 'all') {
            $rows = $rows->where('type', $type)->values();
        } else {
            $rows = $rows->values();
        }

        $summary = $rows->groupBy('type')->map->count()->all();

        return compact('rows', 'summary', 'from', 'to') + [
            'type' => $type ?: 'all',
            'doc' => $doc,
        ];
    }

    /** @return list<array{key:string,label:string}> */
    public static function typeOptions(): array
    {
        return [
            ['key' => 'all', 'label' => __('All exceptions')],
            ['key' => 'missing_hsn', 'label' => __('Missing HSN')],
            ['key' => 'missing_gstin', 'label' => __('Party GSTIN missing')],
            ['key' => 'tax_split', 'label' => __('CGST/SGST vs IGST mismatch')],
            ['key' => 'zero_tax', 'label' => __('Taxable with 0% GST')],
            ['key' => 'rate_override', 'label' => __('Line GST ≠ product GST')],
        ];
    }

    private function saleExceptions(string $from, string $to, ?array $godownIds): Collection
    {
        $sales = Sale::with(['client', 'items.product'])
            ->where('status', 'active')
            ->whereDate('invoice_date', '>=', $from)
            ->whereDate('invoice_date', '<=', $to)
            ->when($godownIds, fn ($q) => $q->whereIn('godown_id', $godownIds))
            ->orderBy('invoice_date')
            ->get();

        $out = collect();
        foreach ($sales as $sale) {
            $party = $sale->client;
            $gstin = trim((string) ($party?->gstin ?? ''));
            if ($sale->is_igst && $gstin === '' && (float) $sale->taxable_total > 0) {
                $out->push($this->row(
                    'sale', $sale->id, $sale->invoice_no, $sale->invoice_date?->toDateString(),
                    $party?->display_name, $gstin, 'missing_gstin',
                    __('Inter-state bill without party GSTIN'),
                    route('sales.show', $sale),
                ));
            }
            if (! $sale->is_igst && (float) $sale->igst_total > 0.004) {
                $out->push($this->row(
                    'sale', $sale->id, $sale->invoice_no, $sale->invoice_date?->toDateString(),
                    $party?->display_name, $gstin, 'tax_split',
                    __('Intra-state bill has IGST amount'),
                    route('sales.show', $sale),
                ));
            }
            if ($sale->is_igst && ((float) $sale->cgst_total + (float) $sale->sgst_total) > 0.004) {
                $out->push($this->row(
                    'sale', $sale->id, $sale->invoice_no, $sale->invoice_date?->toDateString(),
                    $party?->display_name, $gstin, 'tax_split',
                    __('Inter-state bill has CGST/SGST amount'),
                    route('sales.show', $sale),
                ));
            }

            /** @var SaleItem $item */
            foreach ($sale->items as $item) {
                $product = $item->product;
                if (blank($item->hsn_code) && blank($product?->hsn_code)) {
                    $out->push($this->row(
                        'sale', $sale->id, $sale->invoice_no, $sale->invoice_date?->toDateString(),
                        $party?->display_name, $gstin, 'missing_hsn',
                        __('Line without HSN').': '.($product?->display_name ?? '#'),
                        route('sales.show', $sale),
                        $product?->display_name,
                    ));
                }
                $prodGst = (float) ($product?->gst_rate ?? 0);
                $lineGst = (float) $item->gst_rate;
                if (abs($prodGst - $lineGst) > 0.001 && (float) $item->taxable_amount > 0) {
                    $out->push($this->row(
                        'sale', $sale->id, $sale->invoice_no, $sale->invoice_date?->toDateString(),
                        $party?->display_name, $gstin, 'rate_override',
                        __('GST :line% on bill, product :prod%', ['line' => qty($lineGst), 'prod' => qty($prodGst)])
                            .' — '.($product?->display_name ?? ''),
                        route('sales.show', $sale),
                        $product?->display_name,
                    ));
                }
                if ((float) $item->taxable_amount > 0.004 && $lineGst <= 0.001 && $prodGst > 0.001) {
                    $out->push($this->row(
                        'sale', $sale->id, $sale->invoice_no, $sale->invoice_date?->toDateString(),
                        $party?->display_name, $gstin, 'zero_tax',
                        __('Taxable line at 0% but product GST is :g%', ['g' => qty($prodGst)])
                            .' — '.($product?->display_name ?? ''),
                        route('sales.show', $sale),
                        $product?->display_name,
                    ));
                }
            }
        }

        return $out;
    }

    private function purchaseExceptions(string $from, string $to, ?array $godownIds): Collection
    {
        $purchases = Purchase::with(['supplier', 'items.product'])
            ->where('status', 'active')
            ->whereDate('purchase_date', '>=', $from)
            ->whereDate('purchase_date', '<=', $to)
            ->when($godownIds, fn ($q) => $q->whereIn('godown_id', $godownIds))
            ->orderBy('purchase_date')
            ->get();

        $out = collect();
        foreach ($purchases as $bill) {
            $party = $bill->supplier;
            $gstin = trim((string) ($party?->gstin ?? ''));
            if ($bill->is_igst && $gstin === '' && (float) $bill->taxable_total > 0) {
                $out->push($this->row(
                    'purchase', $bill->id, $bill->purchase_no, $bill->purchase_date?->toDateString(),
                    $party?->display_name, $gstin, 'missing_gstin',
                    __('Inter-state purchase without supplier GSTIN'),
                    route('purchases.show', $bill),
                ));
            }
            if (! $bill->is_igst && (float) $bill->igst_total > 0.004) {
                $out->push($this->row(
                    'purchase', $bill->id, $bill->purchase_no, $bill->purchase_date?->toDateString(),
                    $party?->display_name, $gstin, 'tax_split',
                    __('Intra-state purchase has IGST amount'),
                    route('purchases.show', $bill),
                ));
            }
            if ($bill->is_igst && ((float) $bill->cgst_total + (float) $bill->sgst_total) > 0.004) {
                $out->push($this->row(
                    'purchase', $bill->id, $bill->purchase_no, $bill->purchase_date?->toDateString(),
                    $party?->display_name, $gstin, 'tax_split',
                    __('Inter-state purchase has CGST/SGST amount'),
                    route('purchases.show', $bill),
                ));
            }

            /** @var PurchaseItem $item */
            foreach ($bill->items as $item) {
                $product = $item->product;
                if (blank($item->hsn_code) && blank($product?->hsn_code)) {
                    $out->push($this->row(
                        'purchase', $bill->id, $bill->purchase_no, $bill->purchase_date?->toDateString(),
                        $party?->display_name, $gstin, 'missing_hsn',
                        __('Line without HSN').': '.($product?->display_name ?? '#'),
                        route('purchases.show', $bill),
                        $product?->display_name,
                    ));
                }
                $prodGst = (float) ($product?->gst_rate ?? 0);
                $lineGst = (float) $item->gst_rate;
                if (abs($prodGst - $lineGst) > 0.001 && (float) $item->taxable_amount > 0) {
                    $out->push($this->row(
                        'purchase', $bill->id, $bill->purchase_no, $bill->purchase_date?->toDateString(),
                        $party?->display_name, $gstin, 'rate_override',
                        __('GST :line% on bill, product :prod%', ['line' => qty($lineGst), 'prod' => qty($prodGst)])
                            .' — '.($product?->display_name ?? ''),
                        route('purchases.show', $bill),
                        $product?->display_name,
                    ));
                }
                if ((float) $item->taxable_amount > 0.004 && $lineGst <= 0.001 && $prodGst > 0.001) {
                    $out->push($this->row(
                        'purchase', $bill->id, $bill->purchase_no, $bill->purchase_date?->toDateString(),
                        $party?->display_name, $gstin, 'zero_tax',
                        __('Taxable line at 0% but product GST is :g%', ['g' => qty($prodGst)])
                            .' — '.($product?->display_name ?? ''),
                        route('purchases.show', $bill),
                        $product?->display_name,
                    ));
                }
            }
        }

        return $out;
    }

    private function row(
        string $doc,
        int $id,
        string $number,
        ?string $date,
        ?string $party,
        string $gstin,
        string $type,
        string $detail,
        string $url,
        ?string $item = null,
    ): array {
        return [
            'doc' => $doc,
            'id' => $id,
            'number' => $number,
            'date' => $date,
            'party' => $party ?: '—',
            'gstin' => $gstin !== '' ? $gstin : '—',
            'type' => $type,
            'type_label' => collect(self::typeOptions())->firstWhere('key', $type)['label'] ?? $type,
            'detail' => $detail,
            'item' => $item,
            'url' => $url,
        ];
    }
}
