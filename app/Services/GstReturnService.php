<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SaleReturn;
use Illuminate\Support\Collection;

/**
 * India GST compliance packs from posted trade vouchers (GSTR-1 style outward + GSTR-2 style inward + HSN).
 */
class GstReturnService
{
    /**
     * @return array{
     *   from:string,to:string,shop_state:?string,
     *   summary:array<string,float|int>,
     *   b2b:list<array>,b2c:list<array>,b2cl:list<array>,
     *   cdnr:list<array>,cdnur:list<array>,
     *   purchases:list<array>,purchase_returns:list<array>,
     *   hsn_out:list<array>,hsn_in:list<array>
     * }
     */
    public function build(string $from, string $to, ?array $godownIds = null): array
    {
        $shopState = CompanyProfile::current()->state_code;
        $b2b = $b2c = $b2cl = $cdnr = $cdnur = [];
        $purchases = $purchaseReturns = [];
        $hsnOut = [];
        $hsnIn = [];
        $sum = [
            'bills' => 0, 'taxable' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0, 'grand' => 0.0,
            'in_bills' => 0, 'in_taxable' => 0.0, 'in_cgst' => 0.0, 'in_sgst' => 0.0, 'in_igst' => 0.0, 'in_grand' => 0.0,
        ];

        $sales = Sale::with(['client', 'items.product.unit'])
            ->where('status', 'active')
            ->whereDate('invoice_date', '>=', $from)->whereDate('invoice_date', '<=', $to)
            ->when($godownIds, fn ($q) => $q->whereIn('godown_id', $godownIds))
            ->orderBy('invoice_date')->orderBy('id')->get();

        foreach ($sales as $sale) {
            $row = $this->saleRow($sale);
            $this->addHsn($hsnOut, $sale->items, 1);
            $sum['bills']++;
            $this->addSum($sum, $row, '');
            if ($row['registered']) {
                $b2b[] = $row;
            } elseif ($row['grand'] >= 250000) {
                $b2cl[] = $row;
            } else {
                $b2c[] = $row;
            }
        }

        $returns = SaleReturn::with(['client', 'sale', 'items.product.unit'])
            ->whereDate('return_date', '>=', $from)->whereDate('return_date', '<=', $to)
            ->when($godownIds, fn ($q) => $q->whereIn('godown_id', $godownIds))
            ->orderBy('return_date')->orderBy('id')->get();

        foreach ($returns as $ret) {
            $row = $this->saleReturnRow($ret);
            $this->addHsn($hsnOut, $ret->items, -1);
            $sum['bills']++;
            $this->addSum($sum, $row, '');
            if ($row['registered']) {
                $cdnr[] = $row;
            } else {
                $cdnur[] = $row;
            }
        }

        $buys = Purchase::with(['supplier', 'items.product.unit'])
            ->where('status', 'active')
            ->whereDate('purchase_date', '>=', $from)->whereDate('purchase_date', '<=', $to)
            ->when($godownIds, fn ($q) => $q->whereIn('godown_id', $godownIds))
            ->orderBy('purchase_date')->orderBy('id')->get();

        foreach ($buys as $p) {
            $row = $this->purchaseRow($p);
            $this->addHsn($hsnIn, $p->items, 1);
            $sum['in_bills']++;
            $this->addSum($sum, $row, 'in_');
            $purchases[] = $row;
        }

        $prns = PurchaseReturn::with(['supplier', 'purchase', 'items.product.unit'])
            ->whereDate('return_date', '>=', $from)->whereDate('return_date', '<=', $to)
            ->when($godownIds, fn ($q) => $q->whereIn('godown_id', $godownIds))
            ->orderBy('return_date')->orderBy('id')->get();

        foreach ($prns as $pr) {
            $row = $this->purchaseReturnRow($pr);
            $this->addHsn($hsnIn, $pr->items, -1);
            $sum['in_bills']++;
            $this->addSum($sum, $row, 'in_');
            $purchaseReturns[] = $row;
        }

        return [
            'from' => $from,
            'to' => $to,
            'shop_state' => $shopState,
            'summary' => $sum,
            'b2b' => $b2b,
            'b2c' => $b2c,
            'b2cl' => $b2cl,
            'cdnr' => $cdnr,
            'cdnur' => $cdnur,
            'purchases' => $purchases,
            'purchase_returns' => $purchaseReturns,
            'hsn_out' => $this->hsnList($hsnOut),
            'hsn_in' => $this->hsnList($hsnIn),
        ];
    }

    private function saleRow(Sale $sale): array
    {
        $party = $sale->client;

        return [
            'number' => $sale->invoice_no,
            'date' => $sale->invoice_date?->toDateString(),
            'party' => $party?->display_name ?? $sale->customer_name,
            'gstin' => $party?->gstin,
            'place' => $sale->supply_state_code ?: $party?->state_code,
            'registered' => filled($party?->gstin),
            'taxable' => (float) $sale->taxable_total,
            'cgst' => (float) $sale->cgst_total,
            'sgst' => (float) $sale->sgst_total,
            'igst' => (float) $sale->igst_total,
            'grand' => (float) $sale->grand_total,
            'credit' => false,
        ];
    }

    private function saleReturnRow(SaleReturn $ret): array
    {
        $party = $ret->client;

        return [
            'number' => $ret->return_no,
            'date' => $ret->return_date?->toDateString(),
            'party' => $party?->display_name,
            'gstin' => $party?->gstin,
            'place' => $ret->sale?->supply_state_code ?: $party?->state_code,
            'registered' => filled($party?->gstin),
            'taxable' => -1 * (float) $ret->taxable_total,
            'cgst' => -1 * (float) $ret->cgst_total,
            'sgst' => -1 * (float) $ret->sgst_total,
            'igst' => -1 * (float) $ret->igst_total,
            'grand' => -1 * (float) $ret->grand_total,
            'ref' => $ret->sale?->invoice_no,
            'credit' => true,
        ];
    }

    private function purchaseRow(Purchase $p): array
    {
        $party = $p->supplier;

        return [
            'number' => $p->purchase_no,
            'date' => $p->purchase_date?->toDateString(),
            'party' => $party?->display_name,
            'gstin' => $party?->gstin,
            'place' => $p->supply_state_code ?: $party?->state_code,
            'registered' => filled($party?->gstin),
            'taxable' => (float) $p->taxable_total,
            'cgst' => (float) $p->cgst_total,
            'sgst' => (float) $p->sgst_total,
            'igst' => (float) $p->igst_total,
            'grand' => (float) $p->grand_total,
            'supplier_invoice' => $p->supplier_invoice_no,
            'credit' => false,
        ];
    }

    private function purchaseReturnRow(PurchaseReturn $pr): array
    {
        $party = $pr->supplier;

        return [
            'number' => $pr->return_no,
            'date' => $pr->return_date?->toDateString(),
            'party' => $party?->display_name,
            'gstin' => $party?->gstin,
            'place' => $pr->purchase?->supply_state_code ?: $party?->state_code,
            'registered' => filled($party?->gstin),
            'taxable' => -1 * (float) $pr->taxable_total,
            'cgst' => -1 * (float) $pr->cgst_total,
            'sgst' => -1 * (float) $pr->sgst_total,
            'igst' => -1 * (float) $pr->igst_total,
            'grand' => -1 * (float) $pr->grand_total,
            'ref' => $pr->purchase?->purchase_no,
            'credit' => true,
        ];
    }

    private function addSum(array &$sum, array $row, string $prefix): void
    {
        $sum[$prefix.'taxable'] = round(($sum[$prefix.'taxable'] ?? 0) + $row['taxable'], 2);
        $sum[$prefix.'cgst'] = round(($sum[$prefix.'cgst'] ?? 0) + $row['cgst'], 2);
        $sum[$prefix.'sgst'] = round(($sum[$prefix.'sgst'] ?? 0) + $row['sgst'], 2);
        $sum[$prefix.'igst'] = round(($sum[$prefix.'igst'] ?? 0) + $row['igst'], 2);
        $sum[$prefix.'grand'] = round(($sum[$prefix.'grand'] ?? 0) + $row['grand'], 2);
    }

    /** @param  Collection<int, mixed>  $items */
    private function addHsn(array &$bag, $items, int $sign): void
    {
        foreach ($items as $it) {
            $hsn = (string) ($it->hsn_code ?: $it->product?->hsn_code ?: 'NA');
            $rate = (float) $it->gst_rate;
            $key = $hsn.'|'.$rate;
            if (! isset($bag[$key])) {
                $bag[$key] = [
                    'hsn' => $hsn,
                    'rate' => $rate,
                    'qty' => 0.0,
                    'unit' => $it->product?->unit?->short_name,
                    'taxable' => 0.0,
                    'cgst' => 0.0,
                    'sgst' => 0.0,
                    'igst' => 0.0,
                ];
            }
            $bag[$key]['qty'] = round($bag[$key]['qty'] + $sign * (float) $it->qty, 3);
            $bag[$key]['taxable'] = round($bag[$key]['taxable'] + $sign * (float) $it->taxable_amount, 2);
            $bag[$key]['cgst'] = round($bag[$key]['cgst'] + $sign * (float) $it->cgst_amount, 2);
            $bag[$key]['sgst'] = round($bag[$key]['sgst'] + $sign * (float) $it->sgst_amount, 2);
            $bag[$key]['igst'] = round($bag[$key]['igst'] + $sign * (float) $it->igst_amount, 2);
        }
    }

    /** @return list<array> */
    private function hsnList(array $bag): array
    {
        $rows = array_values($bag);
        usort($rows, fn ($a, $b) => [$a['hsn'], $a['rate']] <=> [$b['hsn'], $b['rate']]);

        return $rows;
    }
}
