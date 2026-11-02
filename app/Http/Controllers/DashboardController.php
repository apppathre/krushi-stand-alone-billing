<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\Sale;
use App\Services\PartyStatementService;
use App\Services\ReportService;
use App\Services\StockService;
use App\Support\WorkContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Fast shell only — KPIs / figures / recent load over Ajax. */
    public function __invoke(Request $request): View
    {
        return view('dashboard', [
            'workScope' => WorkContext::scope($request),
            'shortcuts' => $this->shortcuts($request),
        ]);
    }

    /** Shop-pulse KPI counts + outstanding totals (JSON). Role-gated like TMS. */
    public function pulse(Request $request, ReportService $reports, PartyStatementService $statements): JsonResponse
    {
        $user = $request->user();
        $stockIds = WorkContext::stockGodownIds($request);
        $showRecv = $this->canSeeReceivables($user);
        $showPay = $this->canSeePayables($user);
        $showStock = $user->canMenu('stock', 'view') || $user->canMenu('products', 'view');

        $parties = ($showRecv || $showPay) ? $reports->outstandingParties() : [
            'receivable' => 0, 'payable' => 0,
            'receivable_rows' => collect(), 'payable_rows' => collect(),
        ];
        $lowCount = $showStock ? $this->lowStockCount($stockIds) : 0;
        $expiryCount = $showStock ? $this->expiryCount($stockIds) : 0;
        $recvAmt = $showRecv ? (float) $parties['receivable'] : 0.0;
        $payAmt = $showPay ? (float) $parties['payable'] : 0.0;
        $openBal = round($recvAmt + $payAmt, 2);

        $licences = $statements->licenceCards(CompanyProfile::current());
        $licenceAlerts = collect($licences)->filter(fn ($c) => $c['expired'] || $c['soon'])->values()->all();

        return response()->json([
            'show_low' => $showStock,
            'show_expiry' => $showStock,
            'show_recv' => $showRecv,
            'show_pay' => $showPay,
            'low_count' => $lowCount,
            'expiry_count' => $expiryCount,
            'recv_count' => $showRecv ? $parties['receivable_rows']->count() : 0,
            'pay_count' => $showPay ? $parties['payable_rows']->count() : 0,
            'receivable' => $recvAmt,
            'payable' => $payAmt,
            'receivable_fmt' => money($recvAmt),
            'payable_fmt' => money($payAmt),
            'open_balance_fmt' => money($openBal),
            'low_hint' => $lowCount > 0 ? __('Click for details') : __('All clear'),
            'expiry_hint' => $expiryCount > 0 ? __('Click for details') : __('No near-expiry batches'),
            'licences' => $licences,
            'licence_alert_count' => count($licenceAlerts),
        ]);
    }

    /** Due-date calendar events for the current month (role-filtered). */
    public function calendar(Request $request, PartyStatementService $statements): JsonResponse
    {
        $user = $request->user();
        $from = (string) $request->query('from', now()->startOfMonth()->toDateString());
        $to = (string) $request->query('to', now()->endOfMonth()->toDateString());
        $events = $statements->calendarDues(
            $from,
            $to,
            $this->canSeeReceivables($user),
            $this->canSeePayables($user),
        );

        // Licence renewals on calendar
        foreach ($statements->licenceCards(CompanyProfile::current()) as $lic) {
            if (! $lic['valid_till'] || $lic['valid_till'] < $from || $lic['valid_till'] > $to) {
                continue;
            }
            $events[] = [
                'date' => $lic['valid_till'],
                'title' => $lic['label'].' · '.__('licence'),
                'amount' => 0,
                'amount_fmt' => $lic['number'],
                'kind' => $lic['expired'] ? 'licence_expired' : 'licence',
                'url' => route('settings.company'),
            ];
        }
        usort($events, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return response()->json(['ok' => true, 'events' => $events]);
    }

    /** Today / month / stock figures (JSON) — no outstanding (pulse owns that). */
    public function figures(Request $request, StockService $stock): JsonResponse
    {
        $user = $request->user();
        $godownIds = $user->accessibleGodownIds();
        $stockIds = WorkContext::stockGodownIds($request);
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $salesBase = Sale::query()->where('status', 'active')->whereIn('godown_id', $godownIds);
        $todayRow = (clone $salesBase)->whereDate('invoice_date', $today)
            ->selectRaw('COALESCE(SUM(grand_total), 0) as total, COUNT(*) as cnt')
            ->first();
        $todaySales = (float) ($todayRow->total ?? 0);
        $todayCount = (int) ($todayRow->cnt ?? 0);
        $monthSales = (float) (clone $salesBase)->whereDate('invoice_date', '>=', $monthStart)->sum('grand_total');
        $monthPurchases = (float) Purchase::where('status', 'active')->whereIn('godown_id', $godownIds)
            ->whereDate('purchase_date', '>=', $monthStart)->sum('grand_total');
        $stockValue = (float) $stock->valueAsOf(null, $stockIds);

        return response()->json([
            'today_sales' => $todaySales,
            'today_sales_fmt' => money($todaySales),
            'today_count' => $todayCount,
            'today_count_label' => __(':count invoices', ['count' => $todayCount]),
            'month_sales' => $monthSales,
            'month_sales_fmt' => money($monthSales),
            'month_purchases' => $monthPurchases,
            'month_purchases_fmt' => money($monthPurchases),
            'stock_value' => $stockValue,
            'stock_value_fmt' => money($stockValue),
            'stock_scope_label' => WorkContext::scope($request) === 'shop' ? __('Whole shop') : __('Selected place'),
        ]);
    }

    /** Recent invoices table fragment (HTML). */
    public function recent(Request $request): View
    {
        $godownIds = $request->user()->accessibleGodownIds();
        $recentSales = Sale::with('client')
            ->whereIn('godown_id', $godownIds)
            ->latest('id')
            ->limit(6)
            ->get();

        return view('dashboard._recent', compact('recentSales'));
    }

    /** Detail list for the low-stock drawer (loaded on demand). */
    public function lowStockAlert(Request $request): View
    {
        $ids = WorkContext::stockGodownIds($request);
        $rows = $this->lowStockQuery($ids)->orderBy('products.name')->limit(50)->get();

        return view('dashboard._low-stock-drawer', [
            'rows' => $rows,
            'workScope' => WorkContext::scope($request),
        ]);
    }

    /** Detail list for the expiry drawer (loaded on demand). */
    public function expiryAlert(Request $request): View
    {
        $ids = WorkContext::stockGodownIds($request);
        $onHand = \App\Support\StockQty::batchBalances($ids);
        $positiveIds = collect($onHand)->filter(fn ($q) => $q > 0.0005)->keys()->all();
        $rows = $positiveIds === []
            ? collect()
            : ProductBatch::with(['product', 'godown'])
                ->whereIn('id', $positiveIds)
                ->whereIn('godown_id', $ids)
                ->whereNotNull('expiry_date')->whereDate('expiry_date', '<=', now()->addDays(60))
                ->orderBy('expiry_date')->limit(50)->get()
                ->each(function (ProductBatch $b) use ($onHand) {
                    $b->qty_on_hand = $onHand[$b->id] ?? 0;
                });

        return view('dashboard._expiry-drawer', [
            'rows' => $rows,
            'workScope' => WorkContext::scope($request),
        ]);
    }

    /** Outstanding parties drawer (receivable or payable). */
    public function outstandingAlert(Request $request, ReportService $reports): View
    {
        $side = $request->query('side') === 'payable' ? 'payable' : 'receivable';
        abort_unless(
            $side === 'payable' ? $this->canSeePayables($request->user()) : $this->canSeeReceivables($request->user()),
            403
        );
        $data = $reports->outstandingParties($side);
        $rows = $side === 'payable' ? $data['payable_rows'] : $data['receivable_rows'];

        return view('dashboard._outstanding-drawer', [
            'side' => $side,
            'rows' => $rows->take(50),
            'total' => $side === 'payable' ? $data['payable'] : $data['receivable'],
        ]);
    }

    private function canSeeReceivables($user): bool
    {
        return $user->canMenu('report_outstanding', 'view')
            || $user->canMenu('sales', 'view')
            || $user->canMenu('receipts', 'view')
            || $user->canMenu('clients', 'view');
    }

    private function canSeePayables($user): bool
    {
        return $user->canMenu('report_outstanding', 'view')
            || $user->canMenu('purchases', 'view')
            || $user->canMenu('payments', 'view')
            || $user->canMenu('suppliers', 'view');
    }

    /** Role-filtered shortcut pins (TMS / ERP style). */
    private function shortcuts(Request $request): array
    {
        $user = $request->user();
        $pins = [
            ['menu' => 'sales', 'action' => 'add', 'route' => 'sales.create', 'label' => __('New Invoice'), 'icon' => 'sales', 'tone' => 'sale'],
            ['menu' => 'purchases', 'action' => 'add', 'route' => 'purchases.create', 'label' => __('New Purchase'), 'icon' => 'purchase', 'tone' => 'ops'],
            ['menu' => 'clients', 'action' => 'view', 'route' => 'clients.index', 'label' => __('Customers'), 'icon' => 'users', 'tone' => 'clients'],
            ['menu' => 'products', 'action' => 'view', 'route' => 'products.index', 'label' => __('Products'), 'icon' => 'box', 'tone' => 'scan'],
            ['menu' => 'stock', 'action' => 'view', 'route' => 'stock.index', 'label' => __('Stock'), 'icon' => 'stock', 'tone' => 'tailor'],
            ['menu' => 'report_outstanding', 'action' => 'view', 'route' => 'reports.outstanding', 'label' => __('Outstanding'), 'icon' => 'outstanding', 'tone' => 'money'],
            ['menu' => 'report_day_book', 'action' => 'view', 'route' => 'reports.day-book', 'label' => __('Day Book'), 'icon' => 'ledger', 'tone' => 'reports'],
            ['menu' => 'receipts', 'action' => 'view', 'route' => 'receipts.index', 'label' => __('Receipt'), 'icon' => 'receipt', 'tone' => 'money'],
            ['menu' => 'payments', 'action' => 'view', 'route' => 'payments.index', 'label' => __('Payment'), 'icon' => 'payment', 'tone' => 'money'],
        ];

        return collect($pins)
            ->filter(fn ($p) => $user->canMenu($p['menu'], $p['action']))
            ->values()
            ->all();
    }

    private function lowStockCount(array $godownIds): int
    {
        if ($godownIds === []) {
            return 0;
        }

        return (int) $this->lowStockQuery($godownIds)->count();
    }

    private function expiryCount(array $godownIds): int
    {
        if ($godownIds === []) {
            return 0;
        }

        $onHand = \App\Support\StockQty::batchBalances($godownIds);
        $positiveIds = collect($onHand)->filter(fn ($q) => $q > 0.0005)->keys()->all();
        if ($positiveIds === []) {
            return 0;
        }

        return ProductBatch::whereIn('id', $positiveIds)
            ->whereIn('godown_id', $godownIds)
            ->whereNotNull('expiry_date')->whereDate('expiry_date', '<=', now()->addDays(60))
            ->count();
    }

    private function lowStockQuery(array $godownIds)
    {
        $onHand = \App\Support\StockQty::productOnHandSqlForGodowns($godownIds);

        return DB::table('products')
            ->where('is_active', true)->where('reorder_level', '>', 0)
            ->whereRaw("COALESCE($onHand, 0) <= products.reorder_level")
            ->selectRaw("products.id, products.name, products.name_mr, products.reorder_level,
                COALESCE($onHand, 0) AS on_hand");
    }
}
