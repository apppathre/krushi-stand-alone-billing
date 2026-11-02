<?php

namespace App\Services;

use App\Models\Ledger;
use App\Models\LedgerEntry;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StockMovement;
use App\Support\LedgerBalance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function __construct(private StockService $stock) {}

    /** Ledger statement with opening, running balance and closing (+ = Dr, - = Cr). Money from ledger_entries only. */
    public function ledgerStatement(Ledger $ledger, string $from, string $to): array
    {
        $before = LedgerBalance::movementTotal($ledger->id, before: $from);
        $opening = round($ledger->signedOpening() + $before, 2);

        $balance = $opening;
        $rows = LedgerEntry::where('ledger_id', $ledger->id)
            ->whereDate('entry_date', '>=', $from)->whereDate('entry_date', '<=', $to)
            ->orderBy('entry_date')->orderBy('id')->get()
            ->map(function ($e) use (&$balance) {
                $balance = round($balance + (float) $e->debit - (float) $e->credit, 2);
                $e->running = $balance;

                return $e;
            });

        return [
            'opening' => $opening,
            'rows' => $rows,
            'debit' => round((float) $rows->sum('debit'), 2),
            'credit' => round((float) $rows->sum('credit'), 2),
            'closing' => $balance,
        ];
    }

    /** All vouchers of a day, plus cash & bank position. */
    public function dayBook(string $date, ?array $godownIds = null): array
    {
        $entries = LedgerEntry::with('ledger')
            ->whereDate('entry_date', $date)
            ->when($godownIds, fn ($q) => $q->whereIn('godown_id', $godownIds))
            ->orderBy('id')->get();

        $vouchers = $entries->groupBy(fn ($e) => $e->voucher_type.'#'.$e->voucher_id);

        $position = [];
        foreach (['cash', 'bank'] as $type) {
            foreach (Ledger::where('type', $type)->get() as $l) {
                $before = (float) LedgerEntry::where('ledger_id', $l->id)->whereDate('entry_date', '<', $date)
                    ->when($godownIds, fn ($q) => $q->whereIn('godown_id', $godownIds))
                    ->value(DB::raw('COALESCE(SUM(debit - credit), 0)'));
                $day = $entries->where('ledger_id', $l->id);
                $opening = round($l->signedOpening() + $before, 2);
                $position[] = [
                    'ledger' => $l,
                    'opening' => $opening,
                    'in' => round((float) $day->sum('debit'), 2),
                    'out' => round((float) $day->sum('credit'), 2),
                    'closing' => round($opening + (float) $day->sum('debit') - (float) $day->sum('credit'), 2),
                ];
            }
        }

        return [
            'vouchers' => $vouchers,
            'debit' => round((float) $entries->sum('debit'), 2),
            'credit' => round((float) $entries->sum('credit'), 2),
            'position' => $position,
        ];
    }

    public function salesRegister(string $from, string $to, ?array $godownIds = null): array
    {
        $sales = Sale::with(['client', 'godown'])->where('status', 'active')
            ->whereDate('invoice_date', '>=', $from)->whereDate('invoice_date', '<=', $to)
            ->when($godownIds, fn ($q) => $q->whereIn('godown_id', $godownIds))
            ->orderBy('invoice_date')->orderBy('id')->get();

        $returns = SaleReturn::with(['client', 'sale'])
            ->whereDate('return_date', '>=', $from)->whereDate('return_date', '<=', $to)
            ->when($godownIds, fn ($q) => $q->whereIn('godown_id', $godownIds))
            ->orderBy('return_date')->orderBy('id')->get();

        return [
            'sales' => $sales,
            'returns' => $returns,
            'totals' => $this->registerTotals($sales),
            'return_totals' => $this->registerTotals($returns),
        ];
    }

    public function purchaseRegister(string $from, string $to, ?array $godownIds = null): array
    {
        $purchases = Purchase::with(['supplier', 'godown'])->where('status', 'active')
            ->whereDate('purchase_date', '>=', $from)->whereDate('purchase_date', '<=', $to)
            ->when($godownIds, fn ($q) => $q->whereIn('godown_id', $godownIds))
            ->orderBy('purchase_date')->orderBy('id')->get();

        return ['purchases' => $purchases, 'totals' => $this->registerTotals($purchases)];
    }

    private function registerTotals($rows): array
    {
        $out = [];
        foreach (['taxable_total', 'cgst_total', 'sgst_total', 'igst_total', 'round_off', 'grand_total'] as $k) {
            $out[$k] = round((float) $rows->sum($k), 2);
        }

        return $out;
    }

    /** Simple profit & loss from ledger postings, with stock-adjusted cost of goods sold. */
    public function profitAndLoss(string $from, string $to, ?array $godownIds = null): array
    {
        $net = function (array $types, string $mode) use ($from, $to, $godownIds) {
            return LedgerEntry::query()
                ->join('ledgers', 'ledgers.id', '=', 'ledger_entries.ledger_id')
                ->whereIn('ledgers.type', $types)
                ->whereDate('entry_date', '>=', $from)->whereDate('entry_date', '<=', $to)
                ->when($godownIds, fn ($q) => $q->whereIn('ledger_entries.godown_id', $godownIds))
                ->groupBy('ledgers.id', 'ledgers.name', 'ledgers.name_mr')
                ->selectRaw('ledgers.id, ledgers.name, ledgers.name_mr, SUM(debit) as dr, SUM(credit) as cr')
                ->get()
                ->map(fn ($r) => [
                    'name' => (app()->getLocale() === 'mr' && $r->name_mr) ? $r->name_mr : $r->name,
                    'amount' => round($mode === 'cr' ? $r->cr - $r->dr : $r->dr - $r->cr, 2),
                ]);
        };

        $sales = $net(['sales'], 'cr');
        $purchases = $net(['purchase'], 'dr');
        $salesTotal = round($sales->sum('amount'), 2);
        $purchaseTotal = round($purchases->sum('amount'), 2);

        $dayBefore = Carbon::parse($from)->subDay()->toDateString();
        $openingStock = $this->stock->valueAsOf($dayBefore, $godownIds);
        $closingStock = $this->stock->valueAsOf($to, $godownIds);
        $openingEntries = (float) StockMovement::where('movement_type', 'opening')
            ->whereDate('movement_date', '>=', $from)->whereDate('movement_date', '<=', $to)
            ->when($godownIds, fn ($q) => $q->whereIn('godown_id', $godownIds))
            ->value(DB::raw('COALESCE(SUM(qty_in * rate), 0)'));

        $cogs = round($openingStock + $openingEntries + $purchaseTotal - $closingStock, 2);
        $gross = round($salesTotal - $cogs, 2);

        // Other heads: net debit = expense, net credit = income.
        $others = $net(['expense', 'discount', 'round_off', 'income'], 'dr');
        $expenses = $others->filter(fn ($r) => $r['amount'] > 0)->values();
        $incomes = $others->filter(fn ($r) => $r['amount'] < 0)->map(fn ($r) => ['name' => $r['name'], 'amount' => -$r['amount']])->values();

        $expenseTotal = round($expenses->sum('amount'), 2);
        $incomeTotal = round($incomes->sum('amount'), 2);

        return [
            'sales' => $sales, 'sales_total' => $salesTotal,
            'purchases' => $purchases, 'purchase_total' => $purchaseTotal,
            'opening_stock' => round($openingStock + $openingEntries, 2),
            'closing_stock' => round($closingStock, 2),
            'cogs' => $cogs,
            'gross_profit' => $gross,
            'expenses' => $expenses, 'expense_total' => $expenseTotal,
            'incomes' => $incomes, 'income_total' => $incomeTotal,
            'net_profit' => round($gross + $incomeTotal - $expenseTotal, 2),
        ];
    }

    /**
     * Trial balance as of a date (opening + movements through as_of).
     * Positive signed = Dr closing, negative = Cr closing.
     */
    public function trialBalance(?string $asOf = null): array
    {
        $asOf = $asOf ?: now()->toDateString();

        $rows = Ledger::query()
            ->where('is_active', true)
            ->leftJoin('ledger_entries', function ($j) use ($asOf) {
                $j->on('ledger_entries.ledger_id', '=', 'ledgers.id')
                    ->whereDate('ledger_entries.entry_date', '<=', $asOf);
            })
            ->groupBy(
                'ledgers.id', 'ledgers.name', 'ledgers.name_mr', 'ledgers.family', 'ledgers.type',
                'ledgers.opening_balance', 'ledgers.opening_type'
            )
            ->selectRaw("ledgers.id, ledgers.name, ledgers.name_mr, ledgers.family, ledgers.type,
                (CASE WHEN ledgers.opening_type = 'cr' THEN -ledgers.opening_balance ELSE ledgers.opening_balance END) AS opening_signed,
                COALESCE(SUM(ledger_entries.debit), 0) AS period_dr,
                COALESCE(SUM(ledger_entries.credit), 0) AS period_cr")
            ->orderByRaw("CASE ledgers.family WHEN 'account' THEN 0 ELSE 1 END")
            ->orderBy('ledgers.type')
            ->orderBy('ledgers.name')
            ->get()
            ->map(function ($r) {
                $opening = round((float) $r->opening_signed, 2);
                // period_dr/cr already include all movements to as_of; closing = opening + dr - cr
                // but SUM includes all entries — opening is separate. Good.
                $dr = round((float) $r->period_dr, 2);
                $cr = round((float) $r->period_cr, 2);
                $closing = round($opening + $dr - $cr, 2);
                $name = (app()->getLocale() === 'mr' && $r->name_mr) ? $r->name_mr : $r->name;

                return (object) [
                    'id' => $r->id,
                    'name' => $name,
                    'family' => $r->family,
                    'type' => $r->type,
                    'opening_dr' => $opening >= 0 ? $opening : 0.0,
                    'opening_cr' => $opening < 0 ? abs($opening) : 0.0,
                    'debit' => $dr,
                    'credit' => $cr,
                    'closing_dr' => $closing >= 0.005 ? $closing : 0.0,
                    'closing_cr' => $closing <= -0.005 ? abs($closing) : 0.0,
                    'closing' => $closing,
                ];
            })
            ->filter(fn ($r) => abs($r->closing) >= 0.005 || $r->debit >= 0.005 || $r->credit >= 0.005 || abs($r->opening_dr + $r->opening_cr) >= 0.005)
            ->values();

        return [
            'rows' => $rows,
            'as_of' => $asOf,
            'total_dr' => round((float) $rows->sum('closing_dr'), 2),
            'total_cr' => round((float) $rows->sum('closing_cr'), 2),
        ];
    }

    /**
     * Simple balance sheet for shop owners (flat sections, not Tally groups).
     */
    public function balanceSheet(?string $asOf = null, ?array $godownIds = null): array
    {
        $asOf = $asOf ?: now()->toDateString();
        $tb = $this->trialBalance($asOf);

        $cashBank = $tb['rows']->whereIn('type', ['cash', 'bank'])->values();
        $receivables = $tb['rows']->where('family', 'party')->filter(fn ($r) => $r->closing > 0.004)->values();
        $payables = $tb['rows']->where('family', 'party')->filter(fn ($r) => $r->closing < -0.004)
            ->map(fn ($r) => (object) array_merge((array) $r, ['closing_cr' => abs($r->closing), 'closing_dr' => 0.0]))
            ->values();
        $taxAsset = $tb['rows']->where('type', 'tax')->filter(fn ($r) => $r->closing > 0.004)->values();
        $taxLiability = $tb['rows']->where('type', 'tax')->filter(fn ($r) => $r->closing < -0.004)
            ->map(fn ($r) => (object) ['name' => $r->name, 'amount' => abs($r->closing)])->values();
        $capital = $tb['rows']->whereIn('type', ['capital', 'other'])
            ->filter(fn ($r) => $r->family === 'account' && $r->closing < -0.004)
            ->map(fn ($r) => (object) ['name' => $r->name, 'amount' => abs($r->closing)])->values();

        $fyStart = optional(\App\Models\FinancialYear::forDate($asOf))->start_date?->toDateString() ?: $asOf;
        // Keep P&L / closing stock on the same global books as trial balance (ignore godown filter).
        $pnl = $this->profitAndLoss($fyStart, $asOf, null);
        $netProfit = (float) $pnl['net_profit'];
        $closingStock = (float) $pnl['closing_stock'];

        $assets = [
            ['name' => __('Cash & bank'), 'amount' => round((float) $cashBank->sum('closing_dr'), 2)],
            ['name' => __('Receivables'), 'amount' => round((float) $receivables->sum('closing_dr'), 2)],
            ['name' => __('Closing stock'), 'amount' => round($closingStock, 2)],
            ['name' => __('Tax credit / other assets'), 'amount' => round((float) $taxAsset->sum('closing_dr'), 2)],
        ];
        $assetTotal = round(collect($assets)->sum('amount'), 2);

        $liabilities = [
            ['name' => __('Payables'), 'amount' => round((float) $payables->sum(fn ($r) => abs($r->closing)), 2)],
            ['name' => __('Tax payable'), 'amount' => round((float) $taxLiability->sum('amount'), 2)],
            ['name' => __('Capital & reserves'), 'amount' => round((float) $capital->sum('amount'), 2)],
            ['name' => $netProfit >= 0 ? __('Net profit (FY to date)') : __('Net loss (FY to date)'), 'amount' => round(abs($netProfit), 2)],
        ];
        // Loss reduces capital side presentation: if loss, show on asset plug is wrong —
        // keep liability total = capital + profit (or capital - loss conceptually by subtracting)
        if ($netProfit < 0) {
            $liabilities[3]['amount'] = 0.0;
            $liabilities[] = ['name' => __('Less: net loss (FY to date)'), 'amount' => round($netProfit, 2)]; // negative
        }
        $liabilityTotal = round(collect($liabilities)->sum('amount'), 2);

        return [
            'as_of' => $asOf,
            'assets' => $assets,
            'asset_total' => $assetTotal,
            'liabilities' => $liabilities,
            'liability_total' => $liabilityTotal,
            'cash_bank_rows' => $cashBank,
            'receivable_rows' => $receivables,
            'payable_rows' => $payables,
            'net_profit' => $netProfit,
            'difference' => round($assetTotal - $liabilityTotal, 2),
        ];
    }

    /** Outstanding receivable / payable across party ledgers. */
    public function outstanding(): array
    {
        $rows = $this->partyBalances();

        return [
            'receivable' => round($rows->whereIn('type', ['client', 'both'])->where('bal', '>', 0)->sum('bal'), 2),
            'payable' => round(abs($rows->whereIn('type', ['supplier', 'both'])->where('bal', '<', 0)->sum('bal')), 2),
        ];
    }

    /** Detailed outstanding parties (positive bal = receivable Dr, negative = payable Cr). */
    public function outstandingParties(?string $side = null): array
    {
        $rows = $this->partyBalances()->filter(fn ($r) => abs((float) $r->bal) >= 0.01)->values();

        $receivable = $rows->whereIn('type', ['client', 'both'])->filter(fn ($r) => (float) $r->bal > 0)
            ->sortByDesc('bal')->values();
        $payable = $rows->whereIn('type', ['supplier', 'both'])->filter(fn ($r) => (float) $r->bal < 0)
            ->sortBy('bal')->values();

        if ($side === 'receivable') {
            $payable = collect();
        } elseif ($side === 'payable') {
            $receivable = collect();
        }

        return [
            'receivable_rows' => $receivable,
            'payable_rows' => $payable,
            'receivable' => round($receivable->sum('bal'), 2),
            'payable' => round(abs($payable->sum('bal')), 2),
        ];
    }

    /**
     * Bill-wise outstanding with ageing buckets + optional simple interest on overdue days.
     *
     * @return array{rows:\Illuminate\Support\Collection, buckets:array, totals:array, as_of:string, interest_pct:float, side:string}
     */
    public function outstandingAgeing(string $side = 'receivable', ?string $asOf = null, float $interestPct = 0): array
    {
        $asOf = $asOf ?: now()->toDateString();
        $asOfDate = \Illuminate\Support\Carbon::parse($asOf)->startOfDay();
        $companyDays = (int) (\App\Models\CompanyProfile::current()->default_credit_days ?? 30);
        $side = in_array($side, ['receivable', 'payable'], true) ? $side : 'receivable';

        if ($side === 'receivable') {
            $bills = \App\Models\Sale::query()
                ->with('client:id,name,name_mr,phone,credit_days')
                ->where('status', 'active')
                ->openDue()
                ->orderBy('invoice_date')
                ->get(['id', 'invoice_no', 'invoice_date', 'due_date', 'credit_days', 'client_ledger_id', 'customer_name', 'grand_total', 'paid_amount', 'returned_amount']);
        } else {
            $bills = \App\Models\Purchase::query()
                ->with('supplier:id,name,name_mr,phone,credit_days')
                ->where('status', 'active')
                ->openDue()
                ->orderBy('purchase_date')
                ->get(['id', 'purchase_no', 'purchase_date', 'due_date', 'credit_days', 'supplier_ledger_id', 'grand_total', 'paid_amount', 'returned_amount']);
        }

        $buckets = ['current' => 0.0, 'b1_30' => 0.0, 'b31_60' => 0.0, 'b61_90' => 0.0, 'b90_plus' => 0.0];
        $rows = $bills->map(function ($bill) use ($side, $asOfDate, $companyDays, $interestPct, &$buckets) {
            $balance = (float) $bill->balance;
            if ($balance <= 0.004) {
                return null;
            }
            if ($side === 'receivable') {
                $billDate = $bill->invoice_date->copy()->startOfDay();
                $party = $bill->client;
                $partyName = $bill->customer_name ?: $party?->display_name;
                $docNo = $bill->invoice_no;
                $partyId = (int) $bill->client_ledger_id;
            } else {
                $billDate = $bill->purchase_date->copy()->startOfDay();
                $party = $bill->supplier;
                $partyName = $party?->display_name;
                $docNo = $bill->purchase_no;
                $partyId = (int) $bill->supplier_ledger_id;
            }

            $creditDays = (int) ($bill->credit_days ?? ($party?->credit_days ?: $companyDays));
            $dueDate = filled($bill->due_date ?? null)
                ? \Carbon\Carbon::parse($bill->due_date)->startOfDay()
                : $billDate->copy()->addDays(max(0, $creditDays));
            $overdueDays = $asOfDate->gt($dueDate) ? $dueDate->diffInDays($asOfDate) : 0;
            $ageDays = $billDate->diffInDays($asOfDate);

            $bucket = match (true) {
                $overdueDays <= 0 => 'current',
                $overdueDays <= 30 => 'b1_30',
                $overdueDays <= 60 => 'b31_60',
                $overdueDays <= 90 => 'b61_90',
                default => 'b90_plus',
            };
            $buckets[$bucket] = round($buckets[$bucket] + $balance, 2);

            $interest = 0.0;
            if ($interestPct > 0 && $overdueDays > 0) {
                $interest = round($balance * ($interestPct / 100) * ($overdueDays / 365), 2);
            }

            return (object) [
                'id' => $bill->id,
                'doc_no' => $docNo,
                'party_id' => $partyId,
                'party_name' => $partyName,
                'phone' => $party?->phone,
                'bill_date' => $billDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'balance' => $balance,
                'age_days' => $ageDays,
                'overdue_days' => $overdueDays,
                'bucket' => $bucket,
                'interest' => $interest,
            ];
        })->filter()->values();

        return [
            'rows' => $rows,
            'buckets' => $buckets,
            'totals' => [
                'balance' => round((float) $rows->sum('balance'), 2),
                'interest' => round((float) $rows->sum('interest'), 2),
            ],
            'as_of' => $asOf,
            'interest_pct' => $interestPct,
            'side' => $side,
        ];
    }

    /**
     * Live stock snapshot (qty + value from stock_movements only).
     *
     * @param  int|array<int, int>|null  $godownId  One place, many places, or null = all (caller should pass accessible ids).
     */
    public function stockReport(int|array|null $godownId = null, ?int $categoryId = null): array
    {
        if (is_array($godownId)) {
            $onHand = \App\Support\StockQty::productOnHandSqlForGodowns($godownId);
            $valueSql = \App\Support\StockQty::productValueSqlForGodowns($godownId);
        } else {
            $onHand = \App\Support\StockQty::productOnHandSql($godownId);
            $valueSql = \App\Support\StockQty::productValueSql($godownId);
        }

        $q = DB::table('products')
            ->join('units', 'units.id', '=', 'products.unit_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('manufacturers', 'manufacturers.id', '=', 'products.manufacturer_id')
            ->where('products.is_active', true)
            ->when($categoryId, fn ($qq) => $qq->where('products.category_id', $categoryId))
            ->selectRaw("products.id, products.code, products.name, products.name_mr, products.purchase_rate, products.sale_rate, products.gst_rate, products.hsn_code, units.short_name as unit,
                categories.name as category_name, manufacturers.name as manufacturer_name,
                COALESCE($onHand, 0) AS qty,
                COALESCE($valueSql, 0) AS value")
            ->orderBy('products.name');

        $rows = $q->get()->map(function ($r) {
            $r->qty = round((float) $r->qty, 3);
            $r->value = round((float) $r->value, 2);
            // Avg cost from movement books (not master purchase_rate) when qty > 0.
            $r->avg_cost = abs($r->qty) > 0.0005 ? round($r->value / $r->qty, 2) : 0.0;

            return $r;
        });

        return [
            'rows' => $rows,
            'qty_total' => round((float) $rows->sum('qty'), 3),
            'value_total' => round((float) $rows->sum('value'), 2),
        ];
    }

    /** Selling price list for active items. */
    public function priceList(?int $categoryId = null, ?int $manufacturerId = null): array
    {
        $rows = DB::table('products')
            ->join('units', 'units.id', '=', 'products.unit_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('manufacturers', 'manufacturers.id', '=', 'products.manufacturer_id')
            ->where('products.is_active', true)
            ->when($categoryId, fn ($q) => $q->where('products.category_id', $categoryId))
            ->when($manufacturerId, fn ($q) => $q->where('products.manufacturer_id', $manufacturerId))
            ->orderBy('products.name')
            ->get([
                'products.code', 'products.name', 'products.name_mr', 'products.hsn_code', 'products.gst_rate',
                'products.mrp', 'products.sale_rate', 'products.purchase_rate',
                'units.short_name as unit', 'categories.name as category_name', 'manufacturers.name as manufacturer_name',
            ]);

        return ['rows' => $rows];
    }

    private function partyBalances()
    {
        return Ledger::query()
            ->where('family', 'party')
            ->where('is_active', true)
            ->leftJoin('ledger_entries', 'ledger_entries.ledger_id', '=', 'ledgers.id')
            ->groupBy('ledgers.id', 'ledgers.name', 'ledgers.name_mr', 'ledgers.phone', 'ledgers.type', 'ledgers.opening_balance', 'ledgers.opening_type')
            ->selectRaw("ledgers.id, ledgers.name, ledgers.name_mr, ledgers.phone, ledgers.type,
                (CASE WHEN ledgers.opening_type = 'cr' THEN -ledgers.opening_balance ELSE ledgers.opening_balance END)
                + COALESCE(SUM(ledger_entries.debit - ledger_entries.credit), 0) AS bal")
            ->get();
    }
}
