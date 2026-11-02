<?php

namespace App\Services;

use App\Models\AccountMap;
use App\Support\TermsHtml;
use App\Models\CompanyProfile;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(
        private GstCalculator $gst,
        private StockService $stock,
        private InvoiceNumberGenerator $numbers,
        private LedgerPoster $poster,
    ) {}

    /**
     * Create an invoice: GST calc, stock issue, ledger posting, optional first payment.
     *
     * @param  array{invoice_date:string, godown_id:int, client_ledger_id?:?int, customer_name?:?string, customer_phone?:?string, supply_state_code?:?string, notes?:?string, items:array, paid_amount?:float, payment_mode?:string, payment_reference?:?string}  $data
     */
    public function create(array $data, ?int $userId = null): Sale
    {
        return DB::transaction(function () use ($data, $userId) {
            $company = CompanyProfile::current();
            $map = AccountMap::current();

            if (empty($data['client_ledger_id'])) {
                throw new \RuntimeException(__('Select a customer ledger. Walk-in is not allowed.'));
            }
            $client = Ledger::findOrFail($data['client_ledger_id']);
            $walkInId = (int) ($map->walk_in_ledger_id ?: 0);
            if ($walkInId > 0 && (int) $client->id === $walkInId) {
                throw new \RuntimeException(__('Select a customer ledger. Walk-in is not allowed.'));
            }
            $supplyState = ($data['supply_state_code'] ?? null) ?: ($client->state_code ?: $company->state_code);
            $inter = $this->gst->isInterState($company->state_code, $supplyState);

            $headerPlace = (int) $data['godown_id'];
            $products = Product::findMany(collect($data['items'])->pluck('product_id'))->keyBy('id');
            $items = [];
            foreach ($data['items'] as $row) {
                $p = $products[$row['product_id']];
                $gst = array_key_exists('gst_rate', $row) && $row['gst_rate'] !== null && $row['gst_rate'] !== ''
                    ? (float) $row['gst_rate']
                    : (float) $p->gst_rate;
                $items[] = [
                    'product_id' => $p->id,
                    'batch_id' => $row['batch_id'] ?? null,
                    'godown_id' => (int) ($row['godown_id'] ?? $headerPlace),
                    'hsn_code' => $p->hsn_code,
                    'qty' => (float) $row['qty'],
                    'rate' => (float) $row['rate'],
                    'discount_pct' => (float) ($row['discount_pct'] ?? 0),
                    'gst_rate' => $gst,
                ];
            }
            $calc = $this->gst->calculate($items, $inter);
            $calc['totals'] = $this->gst->applyBillCharges(
                $calc['totals'],
                (float) ($data['freight_amount'] ?? 0),
                (float) ($data['hammali_amount'] ?? 0),
                (float) ($data['cash_discount'] ?? 0),
            );
            [$creditDays, $dueDate] = $this->resolveCreditTerms(
                $data,
                $client,
                $company,
                (string) $data['invoice_date'],
            );

            $paidNow = min((float) ($data['paid_amount'] ?? 0), (float) $calc['totals']['grand_total']);
            $this->assertCreditLimit($company, $client, (float) $calc['totals']['grand_total'] - $paidNow);

            $salespersonId = $client->salesperson_user_id ? (int) $client->salesperson_user_id : null;
            $commissionPct = null;
            $commissionAmt = 0.0;
            if ($company->commission_enabled && $salespersonId) {
                $commissionPct = filled($client->commission_pct) ? (float) $client->commission_pct : (float) ($company->default_commission_pct ?? 0);
                if ($commissionPct > 0) {
                    $commissionAmt = round((float) $calc['totals']['taxable_total'] * $commissionPct / 100, 2);
                }
            }

            $sale = Sale::create($calc['totals'] + [
                'invoice_no' => $this->numbers->next(
                    'sale',
                    $data['invoice_date'],
                    isset($data['document_series_id']) ? (int) $data['document_series_id'] : null
                ),
                'financial_year_id' => \App\Models\FinancialYear::forDate($data['invoice_date'])->id,
                'invoice_date' => $data['invoice_date'],
                'godown_id' => $data['godown_id'],
                'client_ledger_id' => $client->id,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'supply_state_code' => $supplyState,
                'is_igst' => $inter,
                'notes' => $data['notes'] ?? null,
                'credit_days' => $creditDays,
                'due_date' => $dueDate,
                'terms' => TermsHtml::sanitize($data['terms'] ?? null)
                    ?: TermsHtml::fromCompany($company->invoice_terms),
                'status' => 'active',
                'user_id' => $userId,
                'salesperson_user_id' => $salespersonId,
                'commission_pct' => $commissionPct,
                'commission_amount' => $commissionAmt,
            ]);

            foreach ($calc['lines'] as $line) {
                $linePlace = (int) ($line['godown_id'] ?? $sale->godown_id);
                $alloc = $this->stock->issue(
                    $line['product_id'], $linePlace, $line['qty'], $line['batch_id'],
                    'sale', 'sale', $sale->id, $sale->invoice_date, $sale->invoice_no,
                );
                $cost = round(collect($alloc)->sum(fn ($a) => $a['qty'] * $a['rate']) / max($line['qty'], 0.001), 2);

                $sale->items()->create([
                    'godown_id' => $linePlace,
                    'product_id' => $line['product_id'],
                    'batch_id' => $alloc[0]['batch_id'],
                    'hsn_code' => $line['hsn_code'],
                    'qty' => $line['qty'],
                    'rate' => $line['rate'],
                    'cost_rate' => $cost,
                    'discount_pct' => $line['discount_pct'],
                    'discount_amount' => $line['discount_amount'],
                    'taxable_amount' => $line['taxable_amount'],
                    'gst_rate' => $line['gst_rate'],
                    'cgst_amount' => $line['cgst_amount'],
                    'sgst_amount' => $line['sgst_amount'],
                    'igst_amount' => $line['igst_amount'],
                    'line_total' => $line['line_total'],
                ]);
            }

            $sale = $sale->fresh();
            $this->poster->postSale($sale);
            if ($commissionAmt > 0.005) {
                $this->poster->postSaleCommission($sale, $commissionAmt);
            }

            $paid = min((float) ($data['paid_amount'] ?? 0), (float) $sale->grand_total);
            if ($paid > 0) {
                $this->addPayment($sale, [
                    'amount' => $paid,
                    'ledger_id' => $data['payment_ledger_id'] ?? $data['ledger_id'] ?? null,
                    'mode' => $data['payment_mode'] ?? 'cash',
                    'payment_date' => $data['invoice_date'],
                    'reference' => $data['payment_reference'] ?? null,
                ], $userId);
            }

            return $sale->fresh(['items.product', 'client', 'godown']);
        });
    }

    /** @return array{0:?int, 1:?string} */
    private function resolveCreditTerms(array $data, Ledger $party, CompanyProfile $company, string $billDate): array
    {
        $paid = (float) ($data['paid_amount'] ?? 0);
        $isCredit = ($data['settlement_type'] ?? null) === 'credit'
            || (($data['settlement_type'] ?? null) !== 'cash' && $paid <= 0.005);

        if (! $isCredit) {
            return [null, null];
        }

        $days = array_key_exists('credit_days', $data) && $data['credit_days'] !== null && $data['credit_days'] !== ''
            ? (int) $data['credit_days']
            : (int) ($party->credit_days ?: ($company->default_credit_days ?? 30));
        $days = max(0, min(365, $days));
        $due = \Carbon\Carbon::parse($billDate)->addDays($days)->toDateString();

        return [$days, $due];
    }

    private function assertCreditLimit(CompanyProfile $company, Ledger $client, float $newDue): void
    {
        if (! $company->enforce_credit_limit) {
            return;
        }
        $limit = (float) ($client->credit_limit ?? 0);
        if ($limit <= 0.005) {
            return;
        }
        $bal = \App\Support\LedgerBalance::asOf($client->id);
        $outstanding = max(0, (float) $bal['signed']); // Dr receivable
        if ($outstanding + $newDue - $limit > 0.05) {
            throw ValidationException::withMessages([
                'client_ledger_id' => __('Credit limit exceeded for :party. Limit ₹:limit, outstanding ₹:due.', [
                    'party' => $client->display_name,
                    'limit' => money($limit),
                    'due' => money($outstanding),
                ]),
            ]);
        }
    }

    public function addPayment(Sale $sale, array $data, ?int $userId = null): SalePayment
    {
        return DB::transaction(function () use ($sale, $data, $userId) {
            $sale = Sale::lockForUpdate()->findOrFail($sale->id);
            if ($sale->isCancelled()) {
                throw ValidationException::withMessages(['amount' => __('Invoice is cancelled.')]);
            }
            $amount = round((float) $data['amount'], 2);
            if ($amount <= 0 || $amount - $sale->balance > 0.005) {
                throw ValidationException::withMessages(['amount' => __('Payment cannot exceed the balance due (:bal).', ['bal' => money($sale->balance)])]);
            }

            $target = AccountMap::current()->resolvePaymentLedger(
                isset($data['ledger_id']) ? (int) $data['ledger_id'] : null,
                $data['mode'] ?? null,
            );
            $payment = $sale->payments()->create([
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'mode' => $target['mode'],
                'ledger_id' => $target['ledger_id'],
                'amount' => $amount,
                'reference' => $data['reference'] ?? null,
                'user_id' => $userId,
            ]);
            $sale->update(['paid_amount' => round((float) $sale->paid_amount + $amount, 2)]);
            $this->poster->postSalePayment($payment, $sale);

            return $payment;
        });
    }

    public function cancel(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            $sale = Sale::lockForUpdate()->findOrFail($sale->id);
            if ($sale->isCancelled()) {
                return;
            }
            if ($sale->returns()->exists()) {
                throw ValidationException::withMessages(['cancel' => __('Cannot cancel an invoice that has credit notes.')]);
            }

            $this->stock->reverse('sale', $sale->id, $sale->invoice_date);
            $this->poster->unpost('sale', $sale->id);
            $this->poster->unpost('sale_commission', $sale->id);
            foreach ($sale->payments as $payment) {
                $this->poster->unpost('sale_receipt', $payment->id);
            }
            // Keep payment rows for audit; clear bill due so cancelled invoices never settle again.
            $sale->update(['status' => 'cancelled', 'paid_amount' => 0]);
        });
    }

    /**
     * Credit note for returned goods. $lines: [sale_item_id => qty].
     */
    public function createReturn(Sale $sale, array $lines, string $date, ?string $reason, ?int $userId = null, ?int $seriesId = null): SaleReturn
    {
        return DB::transaction(function () use ($sale, $lines, $date, $reason, $userId, $seriesId) {
            $sale = Sale::lockForUpdate()->findOrFail($sale->id);
            if ($sale->isCancelled()) {
                throw ValidationException::withMessages(['items' => __('Invoice is cancelled.')]);
            }

            $rows = [];
            foreach ($lines as $itemId => $q) {
                $q = (float) $q;
                if ($q <= 0) {
                    continue;
                }
                $item = SaleItem::where('sale_id', $sale->id)->lockForUpdate()->find($itemId);
                if (! $item || $q - $item->returnable_qty > 0.0005) {
                    throw ValidationException::withMessages(['items' => __('Return quantity exceeds the quantity sold.')]);
                }
                $ratio = $q / (float) $item->qty;
                $full = abs($q - (float) $item->qty) < 0.0005;
                $part = fn (string $col) => $full ? (float) $item->$col : round((float) $item->$col * $ratio, 2);

                $rows[] = [
                    'item' => $item,
                    'qty' => $q,
                    'taxable_amount' => $part('taxable_amount'),
                    'cgst_amount' => $part('cgst_amount'),
                    'sgst_amount' => $part('sgst_amount'),
                    'igst_amount' => $part('igst_amount'),
                ];
            }
            if (! $rows) {
                throw ValidationException::withMessages(['items' => __('Enter a return quantity for at least one item.')]);
            }

            $sum = fn (string $k) => round(array_sum(array_column($rows, $k)), 2);
            $before = round($sum('taxable_amount') + $sum('cgst_amount') + $sum('sgst_amount') + $sum('igst_amount'), 2);
            $grand = (float) round($before, 0);

            $return = SaleReturn::create([
                'return_no' => $this->numbers->next('credit_note', $date, $seriesId),
                'financial_year_id' => \App\Models\FinancialYear::forDate($date)->id,
                'sale_id' => $sale->id,
                'client_ledger_id' => $sale->client_ledger_id,
                'godown_id' => $sale->godown_id,
                'return_date' => $date,
                'is_igst' => $sale->is_igst,
                'taxable_total' => $sum('taxable_amount'),
                'cgst_total' => $sum('cgst_amount'),
                'sgst_total' => $sum('sgst_amount'),
                'igst_total' => $sum('igst_amount'),
                'round_off' => round($grand - $before, 2),
                'grand_total' => $grand,
                'reason' => $reason,
                'user_id' => $userId,
            ]);

            foreach ($rows as $r) {
                /** @var SaleItem $item */
                $item = $r['item'];
                $return->items()->create([
                    'sale_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'batch_id' => $item->batch_id,
                    'qty' => $r['qty'],
                    'rate' => $item->rate,
                    'taxable_amount' => $r['taxable_amount'],
                    'gst_rate' => $item->gst_rate,
                    'cgst_amount' => $r['cgst_amount'],
                    'sgst_amount' => $r['sgst_amount'],
                    'igst_amount' => $r['igst_amount'],
                    'line_total' => round($r['taxable_amount'] + $r['cgst_amount'] + $r['sgst_amount'] + $r['igst_amount'], 2),
                ]);

                // Put qty back on the same batches the sale issued from (FIFO-safe).
                $this->stock->returnSaleQty(
                    (int) $sale->id,
                    (int) $item->product_id,
                    (int) ($item->godown_id ?: $sale->godown_id),
                    (float) $r['qty'],
                    $item->batch_id ? (int) $item->batch_id : null,
                    (float) $item->cost_rate,
                    'sale_return',
                    $return->id,
                    $date,
                    $return->return_no,
                );
                $item->update(['returned_qty' => round((float) $item->returned_qty + $r['qty'], 3)]);
            }

            $this->poster->postSaleReturn($return);
            $sale->update([
                'returned_amount' => round((float) ($sale->returned_amount ?? 0) + (float) $return->grand_total, 2),
            ]);

            return $return;
        });
    }
}
