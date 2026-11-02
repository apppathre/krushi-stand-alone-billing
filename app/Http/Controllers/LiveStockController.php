<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CompanyProfile;
use App\Models\Godown;
use App\Models\Product;
use App\Support\StockQty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Public live stock board for shop TV / tablet — unlock with passkey (no staff login).
 */
class LiveStockController extends Controller
{
    public function gate(): View|RedirectResponse
    {
        $company = CompanyProfile::current();
        if (! $company->live_stock_enabled) {
            return view('live-stock.disabled', ['company' => $company]);
        }
        if (session('live_stock_ok') === true) {
            return redirect()->route('live-stock.board');
        }

        return view('live-stock.gate', ['company' => $company]);
    }

    public function unlock(Request $request): RedirectResponse
    {
        $company = CompanyProfile::current();
        if (! $company->live_stock_enabled || blank($company->live_stock_passkey)) {
            return back()->withErrors(['passkey' => __('Live stock screen is turned off.')]);
        }

        $data = $request->validate([
            'passkey' => ['required', 'string', 'max:100'],
        ]);

        if (! Hash::check($data['passkey'], $company->live_stock_passkey)) {
            return back()->withErrors(['passkey' => __('Wrong passkey.')]);
        }

        $request->session()->put('live_stock_ok', true);
        $request->session()->put('live_stock_at', now()->timestamp);

        return redirect()->route('live-stock.board');
    }

    public function lock(Request $request): RedirectResponse
    {
        $request->session()->forget(['live_stock_ok', 'live_stock_at']);

        return redirect()->route('live-stock.gate');
    }

    public function board(Request $request): View|RedirectResponse
    {
        $company = CompanyProfile::current();
        if (! $company->live_stock_enabled) {
            return view('live-stock.disabled', ['company' => $company]);
        }
        if (session('live_stock_ok') !== true) {
            return redirect()->route('live-stock.gate');
        }

        return view('live-stock.board', [
            'company' => $company,
            'payload' => $this->payload($company, $request->input('q'), $request->input('category_id')),
            'categories' => Category::orderBy('name')->get(['id', 'name', 'name_mr']),
            'refresh' => max(10, min(300, (int) ($company->live_stock_refresh_sec ?: 30))),
            'defaultView' => in_array($company->live_stock_default_view, ['table', 'cards'], true)
                ? $company->live_stock_default_view
                : 'table',
            'defaultTheme' => in_array($company->live_stock_theme, ['day', 'night'], true)
                ? $company->live_stock_theme
                : 'day',
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $company = CompanyProfile::current();
        if (! $company->live_stock_enabled || session('live_stock_ok') !== true) {
            return response()->json(['ok' => false, 'message' => 'locked'], 403);
        }

        $payload = $this->payload($company, $request->input('q'), $request->input('category_id'));

        return response()->json([
            'ok' => true,
            'at' => now()->format('H:i:s'),
            'date' => now()->format('d-m-Y'),
            'items' => $payload['items'],
            'low' => $payload['low_count'],
        ]);
    }

    /** Staff dashboard live panel (auth required — no passkey). */
    public function dashboardData(Request $request): JsonResponse
    {
        $company = CompanyProfile::current();
        if (! $company->live_stock_enabled || ! $company->live_stock_on_dashboard) {
            return response()->json(['ok' => false, 'message' => 'off'], 404);
        }

        $payload = $this->payload($company, $request->input('q'), $request->input('category_id'));

        return response()->json([
            'ok' => true,
            'at' => now()->format('H:i:s'),
            'items' => $payload['items'],
            'low' => $payload['low_count'],
            'title' => $payload['title'],
            'godown' => $payload['godown'],
            'show_rate' => $payload['show_rate'],
            'board_url' => url('/live-stock'),
        ]);
    }

    public function payload(CompanyProfile $company, mixed $q = null, mixed $categoryId = null): array
    {
        $gid = $company->live_stock_godown_id ? (int) $company->live_stock_godown_id : null;
        $balances = StockQty::productBalances($gid);
        $showRate = (bool) $company->live_stock_show_rate;

        $products = Product::with(['unit', 'category'])
            ->where('is_active', true)
            ->where('show_on_live_stock', true)
            ->when(filled($q), function ($query) use ($q) {
                $t = '%'.$q.'%';
                $query->where(fn ($w) => $w->where('name', 'like', $t)->orWhere('name_mr', 'like', $t)->orWhere('code', 'like', $t));
            })
            ->when(filled($categoryId), fn ($query) => $query->where('category_id', (int) $categoryId))
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'name_mr', 'sale_rate', 'reorder_level', 'unit_id', 'category_id']);

        $items = [];
        $low = 0;
        foreach ($products as $p) {
            $qty = round((float) ($balances[$p->id] ?? 0), 3);
            $isLow = $p->reorder_level > 0 && $qty <= (float) $p->reorder_level;
            if ($isLow) {
                $low++;
            }
            $items[] = [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->display_name,
                'category' => $p->category?->display_name,
                'qty' => $qty,
                'unit' => $p->unit?->short_name,
                'low' => $isLow,
                'rate' => $showRate ? (float) $p->sale_rate : null,
            ];
        }

        usort($items, function ($a, $b) {
            if ($a['low'] !== $b['low']) {
                return $a['low'] ? -1 : 1;
            }
            if (($a['qty'] > 0) !== ($b['qty'] > 0)) {
                return $a['qty'] > 0 ? -1 : 1;
            }

            return strcmp($a['name'], $b['name']);
        });

        $godownName = null;
        if ($gid) {
            $godownName = Godown::find($gid)?->display_name;
        }

        return [
            'items' => $items,
            'low_count' => $low,
            'godown' => $godownName,
            'title' => $company->live_stock_title ?: __('Live stock'),
            'show_rate' => $showRate,
        ];
    }
}
