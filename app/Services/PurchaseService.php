<?php

namespace App\Services;

use App\Models\AccountMap;
use App\Support\TermsHtml;
use App\Models\CompanyProfile;
use App\Models\FinancialYear;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use App\Models\PurchaseReturn;
use App\Models\Rack;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\ProductCatalog;

class PurchaseService
{
    public function __construct(
        private GstCalculator $gst,
        private StockService $stock,
        private InvoiceNumberGenerator $numbers,
        private LedgerPoster $poster,
    ) {}

    /**
     * Record a purchase with batch lines: GST calc, stock receipt, ledger posting, optional payment.
     */
    public function create(array $data, ?int $userId = null): Purchase
    {
        return DB::transaction(function () use ($data, $userId) {
            $company = CompanyProfile::current();
            $supplier = Ledger::findOrFail($data['supplier_ledger_id']);
            $supplyState = ($data['supply_state_code'] ?? null) ?: ($supplier->state_code ?: $company->state_code);
            $inter = $this->gst->isInterState($company->state_code, $supplyState);

            $headerPlace = (int) $data['godown_id'];
            $products = Product::findMany(collect($data['items'])->pluck('product_id'))->keyBy('id');
            $placeIds = collect($data['items'])
                ->map(fn ($row) => (int) ($row['godown_id'] ?? $headerPlace))
                ->push($headerPlace)
                ->unique()
                ->values()
                ->all();
            $defaultRacks = app(ProductCatalog::class)->defaultRackByPlace($placeIds);
            $items = [];
            foreach ($data['items'] as $row) {
                $p = $products[$row['product_id']];
                $gst = array_key_exists('gst_rate', $row) && $row['gst_rate'] !== null && $row['gst_rate'] !== ''
                    ? (float) $row['gst_rate']
                    : (float) $p->gst_rate;
                $linePlace = (int) ($row['godown_id'] ?? $headerPlace);
                $rackId = filled($row['rack_id'] ?? null)
                    ? (int) $row['rack_id']
                    : ($defaultRacks[$linePlace] ?? null);
                $items[] = [
                    'product_id' => $p->id,
                    'godown_id' => $linePlace,
                    'rack_id' => $rackId,
                    'batch_no' => $row['batch_no'] ?? null,
                    'mfg_date' => $row['mfg_date'] ?? null,
                    'expiry_date' => $row['expiry_date'] ?? null,
                    'hsn_code' => $p->hsn_code,
                    'qty' => (float) $row['qty'],
                    'rate' => (float) $row['rate'],
                    'mrp' => (float) ($row['mrp'] ?? 0),
                    'discount_pct' => (float) ($row['discount_pct'] ?? 0),
                    'gst_rate' => $gst,
                    'batch_required' => $p->batch_required,
                ];
                if ($p->batch_required && blank($row['batch_no'] ?? null)) {
                    throw ValidationException::withMessages(['items' => __('Batch number is required for :product.', ['product' => $p->display_name])]);
                }
                $rackId = $items[array_key_last($items)]['rack_id'] ?? null;
                $linePlace = $items[array_key_last($items)]['godown_id'];
                if ($rackId && ! Rack::where('id', $rackId)->where('godown_id', $linePlace)->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages(['items' => __('Rack must belong to the selected place for :product.', ['product' => $p->display_name])]);
                }
            }
            $calc = $this->gst->calculate($items, $inter);
            $calc['totals'] = $this->gst->applyBillCharges(
                $calc['totals'],
                (float) ($data['freight_amount'] ?? 0),
                (float) ($data['hammali_amount'] ?? 0),
                (float) ($data['cash_discount'] ?? 0),
            );

            $orderId = filled($data['purchase_order_id'] ?? null) ? (int) $data['purchase_order_id'] : null;
            if ($orderId) {
                $po = PurchaseOrder::lockForUpdate()->find($orderId);
                if (! $po || ! $po->isOpen()) {
                    throw ValidationException::withMessages([
                        'purchase_order_id' => __('Purchase order is not open for conversion.'),
                    ]);
                }
            }

            $settlement = ($data['settlement_type'] ?? null) === 'cash' ? 'cash' : 'credit';
            [$creditDays, $dueDate] = $settlement === 'credit'
                ? $this->resolveCreditTerms($data, $supplier, $company, (string) $data['purchase_date'])
                : [null, null];

            $purchase = Purchase::create($calc['totals'] + [
                'purchase_no' => $this->numbers->next(
                    'purchase',
                    $data['purchase_date'],
                    isset($data['document_series_id']) ? (int) $data['document_series_id'] : null
                ),
                'financial_year_id' => FinancialYear::forDate($data['purchase_date'])->id,
                'purchase_date' => $data['purchase_date'],
                'supplier_invoice_no' => $data['supplier_invoice_no'] ?? null,
                'supplier_invoice_date' => $data['supplier_invoice_date'] ?? null,
                'godown_id' => $data['godown_id'],
                'supplier_ledger_id' => $supplier->id,
                'purchase_order_id' => $orderId,
                'supply_state_code' => $supplyState,
                'is_igst' => $inter,
                'notes' => $data['notes'] ?? null,
                'credit_days' => $creditDays,
                'due_date' => $dueDate,
                'terms' => TermsHtml::sanitize($data['terms'] ?? null)
                    ?: TermsHtml::fromCompany($company->invoice_terms),
                'settlement_type' => $settlement,
                'status' => 'active',
                'user_id' => $userId,
            ]);

            foreach ($calc['lines'] as $line) {
                $linePlace = (int) ($line['godown_id'] ?? $purchase->godown_id);
                $netRate = $line['qty'] > 0 ? round($line['taxable_amount'] / $line['qty'], 2) : $line['rate'];
                $batch = $this->stock->receive([
                    'product_id' => $line['product_id'],
                    'godown_id' => $linePlace,
                    'rack_id' => $line['rack_id'] ?? null,
                    'batch_no' => $line['batch_no'],
                    'mfg_date' => $line['mfg_date'],
                    'expiry_date' => $line['expiry_date'],
                    'qty' => $line['qty'],
                    'rate' => $netRate,
                    'mrp' => $line['mrp'],
                    'type' => 'purchase',
                    'ref_type' => 'purchase',
                    'ref_id' => $purchase->id,
                    'date' => $purchase->purchase_date,
                    'narration' => $purchase->purchase_no,
                ]);

                $purchase->items()->create([
                    'godown_id' => $linePlace,
                    'product_id' => $line['product_id'],
                    'batch_id' => $batch->id,
                    'rack_id' => $batch->rack_id,
                    'batch_no' => $batch->batch_no,
                    'mfg_date' => $line['mfg_date'],
                    'expiry_date' => $line['expiry_date'],
                    'hsn_code' => $line['hsn_code'],
                    'qty' => $line['qty'],
                    'rate' => $line['rate'],
                    'mrp' => $line['mrp'],
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

            $this->poster->postPurchase($purchase->fresh());

            $paid = $settlement === 'cash'
                ? min((float) ($data['paid_amount'] ?? 0), (float) $purchase->grand_total)
                : 0.0;
            if ($paid > 0) {
                $this->addPayment($purchase, [
                    'amount' => $paid,
                    'ledger_id' => $data['payment_ledger_id'] ?? $data['ledger_id'] ?? null,
                    'mode' => $data['payment_mode'] ?? 'cash',
                    'payment_date' => $data['purchase_date'],
                    'reference' => $data['payment_reference'] ?? null,
                ]);
            }

            if ($orderId) {
                app(PurchaseOrderService::class)->markConverted(
                    PurchaseOrder::findOrFail($orderId),
                    $purchase->id
                );
            }

            return $purchase->fresh(['items.product', 'supplier', 'godown']);
        });
    }

    /** @return array{0:?int, 1:?string} */
    private function resolveCreditTerms(array $data, Ledger $party, CompanyProfile $company, string $billDate): array
    {
        $days = array_key_exists('credit_days', $data) && $data['credit_days'] !== null && $data['credit_days'] !== ''
            ? (int) $data['credit_days']
            : (int) ($party->credit_days ?: ($company->default_credit_days ?? 30));
        $days = max(0, min(365, $days));
        $due = \Carbon\Carbon::parse($billDate)->addDays($days)->toDateString();

        return [$days, $due];
    }

    public function addPayment(Purchase $purchase, array $data): PurchasePayment
    {
        return DB::transaction(function () use ($purchase, $data) {
            $purchase = Purchase::lockForUpdate()->findOrFail($purchase->id);
            if ($purchase->isCancelled()) {
                throw ValidationException::withMessages(['amount' => __('Purchase is cancelled.')]);
            }
            $amount = round((float) $data['amount'], 2);
            if ($amount <= 0 || $amount - $purchase->balance > 0.005) {
                throw ValidationException::withMessages(['amount' => __('Payment cannot exceed the balance due (:bal).', ['bal' => money($purchase->balance)])]);
            }

            $target = AccountMap::current()->resolvePaymentLedger(
                isset($data['ledger_id']) ? (int) $data['ledger_id'] : null,
                $data['mode'] ?? null,
            );
            $payment = $purchase->payments()->create([
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'mode' => $target['mode'],
                'ledger_id' => $target['ledger_id'],
                'amount' => $amount,
                'reference' => $data['reference'] ?? null,
            ]);
            $purchase->update(['paid_amount' => round((float) $purchase->paid_amount + $amount, 2)]);
            $this->poster->postPurchasePayment($payment, $purchase);

            return $payment;
        });
    }

    public function cancel(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            $purchase = Purchase::lockForUpdate()->findOrFail($purchase->id);
            if ($purchase->isCancelled()) {
                return;
            }
            if ($purchase->returns()->exists()) {
                throw ValidationException::withMessages([
                    'cancel' => __('Cannot cancel a purchase that has debit notes / returns.'),
                ]);
            }
            $this->stock->reverse('purchase', $purchase->id, $purchase->purchase_date);
            $this->poster->unpost('purchase', $purchase->id);
            foreach ($purchase->payments as $payment) {
                $this->poster->unpost('purchase_payment', $payment->id);
            }
            $purchase->update(['status' => 'cancelled', 'paid_amount' => 0]);
        });
    }

    /**
     * Debit note / purchase return against a bill. $lines: [purchase_item_id => qty].
     */
    public function createReturn(Purchase $purchase, array $lines, string $date, ?string $reason, ?int $userId = null, ?int $seriesId = null): PurchaseReturn
    {
        return DB::transaction(function () use ($purchase, $lines, $date, $reason, $userId, $seriesId) {
            $purchase = Purchase::lockForUpdate()->findOrFail($purchase->id);
            if ($purchase->isCancelled()) {
                throw ValidationException::withMessages(['items' => __('Purchase is cancelled.')]);
            }

            $rows = [];
            foreach ($lines as $itemId => $q) {
                $q = (float) $q;
                if ($q <= 0) {
                    continue;
                }
                $item = PurchaseItem::where('purchase_id', $purchase->id)->lockForUpdate()->find($itemId);
                if (! $item || $q - $item->returnable_qty > 0.0005) {
                    throw ValidationException::withMessages(['items' => __('Return quantity exceeds the quantity purchased.')]);
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

            $return = PurchaseReturn::create([
                'return_no' => $this->numbers->next('purchase_return', $date, $seriesId),
                'financial_year_id' => FinancialYear::forDate($date)->id,
                'purchase_id' => $purchase->id,
                'supplier_ledger_id' => $purchase->supplier_ledger_id,
                'godown_id' => $purchase->godown_id,
                'return_date' => $date,
                'is_igst' => $purchase->is_igst,
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
                /** @var PurchaseItem $item */
                $item = $r['item'];
                $placeId = (int) ($item->godown_id ?: $purchase->godown_id);
                $return->items()->create([
                    'purchase_item_id' => $item->id,
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

                $this->stock->issue(
                    $item->product_id,
                    $placeId,
                    $r['qty'],
                    $item->batch_id,
                    'purchase_return',
                    'purchase_return',
                    $return->id,
                    $date,
                    $return->return_no,
                );
                $item->update(['returned_qty' => round((float) ($item->returned_qty ?? 0) + $r['qty'], 3)]);
            }

            $this->poster->postPurchaseReturn($return);
            $purchase->update([
                'returned_amount' => round((float) ($purchase->returned_amount ?? 0) + (float) $return->grand_total, 2),
            ]);

            return $return->fresh(['items.product', 'supplier', 'purchase']);
        });
    }
}
