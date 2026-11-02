<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\FinancialYear;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Support\TermsHtml;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Purchase orders — no stock, no money. Convert to Purchase when goods arrive.
 */
class PurchaseOrderService
{
    public function __construct(
        private GstCalculator $gst,
        private InvoiceNumberGenerator $numbers,
    ) {}

    public function create(array $data, ?int $userId = null): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $userId) {
            [$calc, $meta] = $this->buildCalc($data);

            $order = PurchaseOrder::create($calc['totals'] + $meta + [
                'order_no' => $this->numbers->next(
                    'purchase_order',
                    $data['order_date'],
                    isset($data['document_series_id']) ? (int) $data['document_series_id'] : null
                ),
                'format_id' => isset($data['document_series_id']) ? (int) $data['document_series_id'] : null,
                'financial_year_id' => FinancialYear::forDate($data['order_date'])->id,
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'godown_id' => (int) $data['godown_id'],
                'supplier_ledger_id' => (int) $data['supplier_ledger_id'],
                'notes' => $data['notes'] ?? null,
                'terms' => TermsHtml::sanitize($data['terms'] ?? null)
                    ?: TermsHtml::fromCompany(CompanyProfile::current()->invoice_terms),
                'status' => 'open',
                'user_id' => $userId,
            ]);

            $this->writeLines($order, $calc['lines']);

            return $order->fresh(['items.product', 'supplier', 'godown']);
        });
    }

    public function update(PurchaseOrder $order, array $data, ?int $userId = null): PurchaseOrder
    {
        if (! $order->isOpen()) {
            throw ValidationException::withMessages(['order' => __('Only open purchase orders can be amended.')]);
        }

        return DB::transaction(function () use ($order, $data, $userId) {
            $order = PurchaseOrder::lockForUpdate()->findOrFail($order->id);
            [$calc, $meta] = $this->buildCalc($data);

            $order->update($calc['totals'] + $meta + [
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'godown_id' => (int) $data['godown_id'],
                'supplier_ledger_id' => (int) $data['supplier_ledger_id'],
                'notes' => $data['notes'] ?? null,
                'terms' => TermsHtml::sanitize($data['terms'] ?? null) ?: $order->terms,
                'user_id' => $userId ?? $order->user_id,
                'financial_year_id' => FinancialYear::forDate($data['order_date'])->id,
            ]);

            $order->items()->delete();
            $this->writeLines($order, $calc['lines']);

            return $order->fresh(['items.product', 'supplier', 'godown']);
        });
    }

    public function cancel(PurchaseOrder $order): void
    {
        if ($order->status === 'converted') {
            throw ValidationException::withMessages(['order' => __('Converted purchase order cannot be cancelled.')]);
        }
        if ($order->isCancelled()) {
            return;
        }
        $order->update(['status' => 'cancelled']);
    }

    public function markConverted(PurchaseOrder $order, int $purchaseId): void
    {
        $order->update([
            'status' => 'converted',
            'converted_purchase_id' => $purchaseId,
        ]);
    }

    /** @return array{0: array{lines:array,totals:array}, 1: array{supply_state_code:?string,is_igst:bool}} */
    private function buildCalc(array $data): array
    {
        $company = CompanyProfile::current();
        $supplier = Ledger::findOrFail($data['supplier_ledger_id']);
        $supplyState = ($data['supply_state_code'] ?? null) ?: ($supplier->state_code ?: $company->state_code);
        $inter = $this->gst->isInterState($company->state_code, $supplyState);
        $headerPlace = (int) $data['godown_id'];

        $products = Product::findMany(collect($data['items'])->pluck('product_id'))->keyBy('id');
        $items = [];
        foreach ($data['items'] as $row) {
            $p = $products[$row['product_id']] ?? null;
            if (! $p) {
                continue;
            }
            $qty = round((float) ($row['qty'] ?? 0), 3);
            if ($qty <= 0.0005) {
                continue;
            }
            $gst = array_key_exists('gst_rate', $row) && $row['gst_rate'] !== null && $row['gst_rate'] !== ''
                ? (float) $row['gst_rate']
                : (float) $p->gst_rate;
            $items[] = [
                'product_id' => $p->id,
                'godown_id' => (int) ($row['godown_id'] ?? $headerPlace),
                'hsn_code' => $p->hsn_code,
                'qty' => $qty,
                'rate' => (float) ($row['rate'] ?? 0),
                'mrp' => (float) ($row['mrp'] ?? 0),
                'discount_pct' => (float) ($row['discount_pct'] ?? 0),
                'gst_rate' => $gst,
            ];
        }
        if ($items === []) {
            throw ValidationException::withMessages(['items' => __('Add at least one line with quantity.')]);
        }

        return [
            $this->gst->calculate($items, $inter),
            ['supply_state_code' => $supplyState, 'is_igst' => $inter],
        ];
    }

    private function writeLines(PurchaseOrder $order, array $lines): void
    {
        foreach ($lines as $line) {
            $order->items()->create([
                'product_id' => $line['product_id'],
                'godown_id' => $line['godown_id'] ?? $order->godown_id,
                'hsn_code' => $line['hsn_code'] ?? null,
                'qty' => $line['qty'],
                'rate' => $line['rate'],
                'mrp' => $line['mrp'] ?? 0,
                'discount_pct' => $line['discount_pct'] ?? 0,
                'discount_amount' => $line['discount_amount'] ?? 0,
                'taxable_amount' => $line['taxable_amount'],
                'gst_rate' => $line['gst_rate'],
                'cgst_amount' => $line['cgst_amount'],
                'sgst_amount' => $line['sgst_amount'],
                'igst_amount' => $line['igst_amount'],
                'line_total' => $line['line_total'],
            ]);
        }
    }
}
