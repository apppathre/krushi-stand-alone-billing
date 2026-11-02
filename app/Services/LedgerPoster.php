<?php

namespace App\Services;

use App\Models\AccountMap;
use App\Models\InventoryMap;
use App\Models\LedgerEntry;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use Illuminate\Validation\ValidationException;

/**
 * Double-entry posting: every voucher produces balanced debit/credit rows in ledger_entries
 * (single money log). Balances = ledger opening + SUM(debit − credit).
 */
class LedgerPoster
{
    public function postSale(Sale $sale): void
    {
        $sale->loadMissing('items.product');
        $m = AccountMap::current();
        $rows = [];

        $rows[] = $this->dr($sale->client_ledger_id, $sale->grand_total, __('Sale :no', ['no' => $sale->invoice_no]));

        // Credit sales ledgers by gross value per resolved ledger.
        foreach ($this->groupByLedger($sale->items, 'sales_ledger_id', $m) as $ledgerId => $gross) {
            $rows[] = $this->cr($ledgerId, $gross, __('Sales'));
        }
        $lineDisc = (float) $sale->discount_total;
        $cashDisc = (float) ($sale->cash_discount ?? 0);
        $discTotal = round($lineDisc + $cashDisc, 2);
        if ($discTotal > 0) {
            if ($m->discount_ledger_id) {
                $rows[] = $this->dr($m->discount_ledger_id, $discTotal, $cashDisc > 0 ? __('Discount / cash discount') : __('Discount allowed'));
            } else {
                $this->reduceCredits($rows, $discTotal);
            }
        }
        $this->billCharge($rows, 'cr', (float) ($sale->freight_amount ?? 0), $m->freight_ledger_id, __('Freight'), 'freight_amount');
        $this->billCharge($rows, 'cr', (float) ($sale->hammali_amount ?? 0), $m->hammali_ledger_id, __('Hammali'), 'hammali_amount');
        $this->tax($rows, 'cr', $sale->cgst_total, $sale->sgst_total, $sale->igst_total, $m, 'output');
        $this->roundOff($rows, (float) $sale->round_off, 'cr', $m);

        $this->commit($rows, 'sale', $sale->id, $sale->invoice_no, $sale->invoice_date, $sale->godown_id);
    }

    public function postSalePayment(SalePayment $payment, Sale $sale): void
    {
        $narr = __('Receipt against :no', ['no' => $sale->invoice_no]);
        if (filled($payment->reference)) {
            $narr .= ' · '.$payment->reference;
        }
        $rows = [
            $this->dr($payment->ledger_id, $payment->amount, $narr),
            $this->cr($sale->client_ledger_id, $payment->amount, $narr),
        ];
        $this->commit($rows, 'sale_receipt', $payment->id, $sale->invoice_no, $payment->payment_date, $sale->godown_id);
    }

    public function postPurchase(Purchase $purchase): void
    {
        $purchase->loadMissing('items.product');
        $m = AccountMap::current();
        $rows = [];

        foreach ($this->groupByLedger($purchase->items, 'purchase_ledger_id', $m) as $ledgerId => $gross) {
            $rows[] = $this->dr($ledgerId, $gross, __('Purchase'));
        }
        $this->billCharge($rows, 'dr', (float) ($purchase->freight_amount ?? 0), $m->freight_ledger_id, __('Freight'), 'freight_amount');
        $this->billCharge($rows, 'dr', (float) ($purchase->hammali_amount ?? 0), $m->hammali_ledger_id, __('Hammali'), 'hammali_amount');
        $this->tax($rows, 'dr', $purchase->cgst_total, $purchase->sgst_total, $purchase->igst_total, $m, 'input');
        $this->roundOff($rows, (float) $purchase->round_off, 'dr', $m);

        $lineDisc = (float) $purchase->discount_total;
        $cashDisc = (float) ($purchase->cash_discount ?? 0);
        $discTotal = round($lineDisc + $cashDisc, 2);
        if ($discTotal > 0) {
            if ($m->discount_ledger_id) {
                $rows[] = $this->cr($m->discount_ledger_id, $discTotal, $cashDisc > 0 ? __('Discount / cash discount') : __('Discount received'));
            } else {
                $this->reduceDebits($rows, $discTotal);
            }
        }
        $rows[] = $this->cr($purchase->supplier_ledger_id, $purchase->grand_total, __('Purchase :no', ['no' => $purchase->purchase_no]));

        $this->commit($rows, 'purchase', $purchase->id, $purchase->purchase_no, $purchase->purchase_date, $purchase->godown_id);
    }

    public function postPurchasePayment(PurchasePayment $payment, Purchase $purchase): void
    {
        $narr = __('Payment against :no', ['no' => $purchase->purchase_no]);
        if (filled($payment->reference)) {
            $narr .= ' · '.$payment->reference;
        }
        $rows = [
            $this->dr($purchase->supplier_ledger_id, $payment->amount, $narr),
            $this->cr($payment->ledger_id, $payment->amount, $narr),
        ];
        $this->commit($rows, 'purchase_payment', $payment->id, $purchase->purchase_no, $payment->payment_date, $purchase->godown_id);
    }

    public function postSaleReturn(SaleReturn $return): void
    {
        $m = AccountMap::current();
        $rows = [$this->dr($m->require('sales_return_ledger_id'), $return->taxable_total, __('Sales return'))];

        $this->tax($rows, 'dr', $return->cgst_total, $return->sgst_total, $return->igst_total, $m, 'output');
        $this->roundOff($rows, (float) $return->round_off, 'dr', $m);
        $rows[] = $this->cr($return->client_ledger_id, $return->grand_total, __('Credit note :no', ['no' => $return->return_no]));

        $this->commit($rows, 'sale_return', $return->id, $return->return_no, $return->return_date, $return->godown_id);
    }

    /** Salesperson commission: Dr expense, Cr payable. */
    public function postSaleCommission(Sale $sale, float $amount): void
    {
        if ($amount <= 0.005) {
            return;
        }
        $m = AccountMap::current();
        $expense = $m->commission_expense_ledger_id;
        $payable = $m->commission_payable_ledger_id;
        if (! $expense || ! $payable) {
            throw ValidationException::withMessages([
                'commission' => __('Map commission expense and payable ledgers under Owner / App control.'),
            ]);
        }
        $rows = [
            $this->dr((int) $expense, $amount, __('Commission :no', ['no' => $sale->invoice_no])),
            $this->cr((int) $payable, $amount, __('Commission :no', ['no' => $sale->invoice_no])),
        ];
        $this->commit($rows, 'sale_commission', $sale->id, $sale->invoice_no, $sale->invoice_date, $sale->godown_id);
    }

    /** Reverse purchase books: credit purchase/input tax, debit supplier. */
    public function postPurchaseReturn(PurchaseReturn $return): void
    {
        $return->loadMissing('items.product');
        $m = AccountMap::current();
        $rows = [];

        foreach ($this->groupByLedger($return->items, 'purchase_ledger_id', $m) as $ledgerId => $gross) {
            $rows[] = $this->cr($ledgerId, $gross, __('Purchase return'));
        }
        $this->tax($rows, 'cr', $return->cgst_total, $return->sgst_total, $return->igst_total, $m, 'input');
        $this->roundOff($rows, (float) $return->round_off, 'cr', $m);
        $rows[] = $this->dr($return->supplier_ledger_id, $return->grand_total, __('Purchase return :no', ['no' => $return->return_no]));

        $this->commit($rows, 'purchase_return', $return->id, $return->return_no, $return->return_date, $return->godown_id);
    }

    public function unpost(string $voucherType, int $voucherId): void
    {
        LedgerEntry::where('voucher_type', $voucherType)->where('voucher_id', $voucherId)->delete();
    }

    /** Balanced account voucher (payment / receipt / journal). */
    public function postAccountVoucher(\App\Models\AccountVoucher $voucher): void
    {
        $voucher->loadMissing('lines');
        $rows = [];
        foreach ($voucher->lines as $line) {
            $rows[] = [
                'ledger_id' => (int) $line->ledger_id,
                'debit' => round((float) $line->debit, 2),
                'credit' => round((float) $line->credit, 2),
                'narration' => $line->narration ?: $voucher->narration,
            ];
        }
        $this->commit($rows, $voucher->voucher_type, $voucher->id, $voucher->voucher_no, $voucher->voucher_date, $voucher->godown_id);
    }

    // ------------------------------------------------------------------

    private function dr(int $ledgerId, float|string $amount, ?string $narration = null): array
    {
        return ['ledger_id' => $ledgerId, 'debit' => round((float) $amount, 2), 'credit' => 0, 'narration' => $narration];
    }

    private function cr(int $ledgerId, float|string $amount, ?string $narration = null): array
    {
        return ['ledger_id' => $ledgerId, 'debit' => 0, 'credit' => round((float) $amount, 2), 'narration' => $narration];
    }

    /** @return array<int, float> ledger id => gross amount (taxable + discount) */
    private function groupByLedger($items, string $column, AccountMap $m): array
    {
        $default = $m->require($column);
        $grouped = [];
        foreach ($items as $item) {
            /** @var Product $product */
            $product = $item->product;
            $ledgerId = InventoryMap::resolve($product, $column) ?? $default;
            $gross = round((float) $item->taxable_amount + (float) ($item->discount_amount ?? 0), 2);
            $grouped[$ledgerId] = round(($grouped[$ledgerId] ?? 0) + $gross, 2);
        }

        return $grouped;
    }

    private function tax(array &$rows, string $side, $cgst, $sgst, $igst, AccountMap $m, string $kind): void
    {
        $make = $side === 'dr' ? fn ($l, $a, $n) => $this->dr($l, $a, $n) : fn ($l, $a, $n) => $this->cr($l, $a, $n);
        foreach (['cgst' => $cgst, 'sgst' => $sgst, 'igst' => $igst] as $tax => $amount) {
            if ((float) $amount > 0) {
                $rows[] = $make($m->require($tax.'_'.$kind.'_ledger_id'), $amount, strtoupper($tax));
            }
        }
    }

    /** Round-off plug: $side is the side used when round_off is positive. */
    private function roundOff(array &$rows, float $roundOff, string $positiveSide, AccountMap $m): void
    {
        if (abs($roundOff) < 0.005) {
            return;
        }
        $ledger = $m->require('round_off_ledger_id');
        $side = $roundOff > 0 ? $positiveSide : ($positiveSide === 'dr' ? 'cr' : 'dr');
        $rows[] = $side === 'dr' ? $this->dr($ledger, abs($roundOff), __('Round off')) : $this->cr($ledger, abs($roundOff), __('Round off'));
    }

    /** Freight / hammali: require mapped ledger when amount is present. */
    private function billCharge(array &$rows, string $side, float $amount, mixed $ledgerId, string $label, string $field): void
    {
        if ($amount <= 0.005) {
            return;
        }
        if (! $ledgerId) {
            throw ValidationException::withMessages([
                $field => __('Map :label under Settings → Account map before posting this charge.', ['label' => $label]),
            ]);
        }
        $rows[] = $side === 'dr'
            ? $this->dr((int) $ledgerId, $amount, $label)
            : $this->cr((int) $ledgerId, $amount, $label);
    }

    /** No discount ledger configured: net the discount off the largest credit row. */
    private function reduceCredits(array &$rows, float $amount): void
    {
        $idx = null;
        foreach ($rows as $i => $r) {
            if ($r['credit'] > 0 && ($idx === null || $r['credit'] > $rows[$idx]['credit'])) {
                $idx = $i;
            }
        }
        if ($idx !== null) {
            $rows[$idx]['credit'] = round($rows[$idx]['credit'] - $amount, 2);
        }
    }

    private function reduceDebits(array &$rows, float $amount): void
    {
        $idx = null;
        foreach ($rows as $i => $r) {
            if ($r['debit'] > 0 && ($idx === null || $r['debit'] > $rows[$idx]['debit'])) {
                $idx = $i;
            }
        }
        if ($idx !== null) {
            $rows[$idx]['debit'] = round($rows[$idx]['debit'] - $amount, 2);
        }
    }

    private function commit(array $rows, string $type, int $id, ?string $no, $date, ?int $godownId): void
    {
        $dr = round(array_sum(array_column($rows, 'debit')), 2);
        $cr = round(array_sum(array_column($rows, 'credit')), 2);
        if (abs($dr - $cr) > 0.01) {
            throw ValidationException::withMessages([
                'posting' => __('Voucher is out of balance (Dr :dr, Cr :cr).', ['dr' => $dr, 'cr' => $cr]),
            ]);
        }

        $fyId = \App\Models\FinancialYear::forDate($date)->id;
        foreach ($rows as $row) {
            LedgerEntry::create($row + [
                'entry_date' => $date,
                'voucher_type' => $type,
                'voucher_id' => $id,
                'voucher_no' => $no,
                'financial_year_id' => $fyId,
                'godown_id' => $godownId,
            ]);
        }
    }
}
