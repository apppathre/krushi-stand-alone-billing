<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Bulk settle: one lump receipt/payment held against many bills.
 * Oldest selected bills clear first; the last hit may be partial.
 */
class SettlementService
{
    public function __construct(
        private SaleService $sales,
        private PurchaseService $purchases,
    ) {}

    /**
     * Spread one cash amount across bills oldest-first.
     *
     * @param  list<array{id:int,balance:float,number?:string}>  $bills
     * @return array{allocations: list<array{id:int,number:string,balance:float,apply:float,remaining_after:float,full:bool}>, applied: float, leftover: float, selected_balance: float}
     */
    public function allocateLumpSum(array $bills, float $amount): array
    {
        $pool = round(max(0, $amount), 2);
        $selected = 0.0;
        $allocations = [];

        foreach ($bills as $bill) {
            $balance = round((float) ($bill['balance'] ?? 0), 2);
            if ($balance < 0.01) {
                continue;
            }
            $selected = round($selected + $balance, 2);
            $apply = round(min($balance, $pool), 2);
            if ($apply < 0.01) {
                $allocations[] = [
                    'id' => (int) $bill['id'],
                    'number' => (string) ($bill['number'] ?? ''),
                    'balance' => $balance,
                    'apply' => 0.0,
                    'remaining_after' => $balance,
                    'full' => false,
                ];
                continue;
            }
            $pool = round($pool - $apply, 2);
            $remaining = round($balance - $apply, 2);
            $allocations[] = [
                'id' => (int) $bill['id'],
                'number' => (string) ($bill['number'] ?? ''),
                'balance' => $balance,
                'apply' => $apply,
                'remaining_after' => $remaining,
                'full' => $remaining < 0.01,
            ];
        }

        return [
            'allocations' => $allocations,
            'applied' => round(max(0, $amount - $pool), 2),
            'leftover' => round(max(0, $pool), 2),
            'selected_balance' => $selected,
        ];
    }

    /**
     * @param  array{payment_date:string,mode:string,reference?:?string,amount:float,bill_ids:array<int>,allocate?:array<int,float>}  $data
     * @return array{count:int,applied:float,reference:string,numbers:list<string>}
     */
    public function bulkReceipt(array $data, array $godownIds, ?int $userId = null): array
    {
        return DB::transaction(function () use ($data, $godownIds, $userId) {
            $bills = $this->loadOpenSales($data['bill_ids'] ?? [], $godownIds);
            if ($bills->isEmpty()) {
                throw ValidationException::withMessages(['bill_ids' => __('Select at least one open invoice.')]);
            }

            $partyIds = $bills->pluck('client_ledger_id')->unique()->filter()->values();
            if ($partyIds->count() !== 1) {
                throw ValidationException::withMessages(['bill_ids' => __('All invoices must belong to the same customer.')]);
            }

            $plan = $this->planFromRequest($bills->map(fn (Sale $s) => [
                'id' => $s->id,
                'balance' => $s->balance,
                'number' => $s->invoice_no,
            ])->all(), $data);

            $ref = $this->bulkReference($data['reference'] ?? null, 'RCPT');
            $posted = [];
            foreach ($plan['allocations'] as $row) {
                if ($row['apply'] < 0.01) {
                    continue;
                }
                /** @var Sale $sale */
                $sale = $bills->firstWhere('id', $row['id']);
                $this->sales->addPayment($sale, [
                    'amount' => $row['apply'],
                    'ledger_id' => $data['ledger_id'] ?? null,
                    'mode' => $data['mode'] ?? null,
                    'payment_date' => $data['payment_date'],
                    'reference' => $ref,
                ], $userId);
                $posted[] = $sale->invoice_no;
            }

            if ($posted === []) {
                throw ValidationException::withMessages(['amount' => __('Nothing to allocate. Enter an amount and select bills with due balances.')]);
            }

            return [
                'count' => count($posted),
                'applied' => $plan['applied'],
                'reference' => $ref,
                'numbers' => $posted,
            ];
        });
    }

    /**
     * @param  array{payment_date:string,mode:string,reference?:?string,amount:float,bill_ids:array<int>,allocate?:array<int,float>}  $data
     * @return array{count:int,applied:float,reference:string,numbers:list<string>}
     */
    public function bulkPayment(array $data, array $godownIds, ?int $userId = null): array
    {
        return DB::transaction(function () use ($data, $godownIds, $userId) {
            $bills = $this->loadOpenPurchases($data['bill_ids'] ?? [], $godownIds);
            if ($bills->isEmpty()) {
                throw ValidationException::withMessages(['bill_ids' => __('Select at least one open purchase bill.')]);
            }

            $partyIds = $bills->pluck('supplier_ledger_id')->unique()->filter()->values();
            if ($partyIds->count() !== 1) {
                throw ValidationException::withMessages(['bill_ids' => __('All bills must belong to the same supplier.')]);
            }

            $plan = $this->planFromRequest($bills->map(fn (Purchase $p) => [
                'id' => $p->id,
                'balance' => $p->balance,
                'number' => $p->purchase_no,
            ])->all(), $data);

            $ref = $this->bulkReference($data['reference'] ?? null, 'PAY');
            $posted = [];
            foreach ($plan['allocations'] as $row) {
                if ($row['apply'] < 0.01) {
                    continue;
                }
                /** @var Purchase $purchase */
                $purchase = $bills->firstWhere('id', $row['id']);
                $this->purchases->addPayment($purchase, [
                    'amount' => $row['apply'],
                    'ledger_id' => $data['ledger_id'] ?? null,
                    'mode' => $data['mode'] ?? null,
                    'payment_date' => $data['payment_date'],
                    'reference' => $ref,
                ]);
                $posted[] = $purchase->purchase_no;
            }

            if ($posted === []) {
                throw ValidationException::withMessages(['amount' => __('Nothing to allocate. Enter an amount and select bills with due balances.')]);
            }

            return [
                'count' => count($posted),
                'applied' => $plan['applied'],
                'reference' => $ref,
                'numbers' => $posted,
            ];
        });
    }

    /**
     * @param  list<array{id:int,balance:float,number?:string}>  $bills
     * @param  array{amount?:float,allocate?:array<int|string,mixed>}  $data
     * @return array{allocations: list<array{id:int,number:string,balance:float,apply:float,remaining_after:float,full:bool}>, applied: float, leftover: float, selected_balance: float}
     */
    private function planFromRequest(array $bills, array $data): array
    {
        $explicit = [];
        foreach ((array) ($data['allocate'] ?? []) as $id => $amt) {
            $amt = round((float) $amt, 2);
            if ($amt >= 0.01) {
                $explicit[(int) $id] = $amt;
            }
        }

        if ($explicit !== []) {
            $allocations = [];
            $applied = 0.0;
            $selected = 0.0;
            foreach ($bills as $bill) {
                $balance = round((float) $bill['balance'], 2);
                $selected = round($selected + $balance, 2);
                $apply = round(min($balance, (float) ($explicit[(int) $bill['id']] ?? 0)), 2);
                $remaining = round($balance - $apply, 2);
                $allocations[] = [
                    'id' => (int) $bill['id'],
                    'number' => (string) ($bill['number'] ?? ''),
                    'balance' => $balance,
                    'apply' => $apply,
                    'remaining_after' => $remaining,
                    'full' => $apply >= 0.01 && $remaining < 0.01,
                ];
                $applied = round($applied + $apply, 2);
            }
            if ($applied < 0.01) {
                throw ValidationException::withMessages(['amount' => __('Enter allocate amounts greater than zero.')]);
            }

            return [
                'allocations' => $allocations,
                'applied' => $applied,
                'leftover' => 0.0,
                'selected_balance' => $selected,
            ];
        }

        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount < 0.01) {
            throw ValidationException::withMessages(['amount' => __('Enter the lump amount received / paid.')]);
        }

        $plan = $this->allocateLumpSum($bills, $amount);
        if ($plan['applied'] < 0.01) {
            throw ValidationException::withMessages(['amount' => __('Nothing to allocate against the selected bills.')]);
        }
        if ($plan['leftover'] >= 0.01) {
            throw ValidationException::withMessages([
                'amount' => __('Amount exceeds selected dues by :extra. Lower the amount or select more bills.', [
                    'extra' => money($plan['leftover']),
                ]),
            ]);
        }

        return $plan;
    }

    /** @param  list<int>  $ids */
    private function loadOpenSales(array $ids, array $godownIds): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return collect();
        }

        return Sale::query()
            ->whereIn('id', $ids)
            ->where('status', 'active')
            ->whereIn('godown_id', $godownIds)
            ->openDue()
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get()
            ->keyBy('id')
            ->sortBy(fn (Sale $s) => sprintf('%s-%010d', $s->invoice_date->toDateString(), $s->id))
            ->values();
    }

    /** @param  list<int>  $ids */
    private function loadOpenPurchases(array $ids, array $godownIds): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return collect();
        }

        return Purchase::query()
            ->whereIn('id', $ids)
            ->where('status', 'active')
            ->whereIn('godown_id', $godownIds)
            ->openDue()
            ->orderBy('purchase_date')
            ->orderBy('id')
            ->get()
            ->keyBy('id')
            ->sortBy(fn (Purchase $p) => sprintf('%s-%010d', $p->purchase_date->toDateString(), $p->id))
            ->values();
    }

    private function bulkReference(?string $given, string $prefix): string
    {
        $given = trim((string) $given);
        if ($given !== '') {
            return mb_substr($given, 0, 100);
        }

        return $prefix.'-'.now()->format('Ymd-His');
    }
}
