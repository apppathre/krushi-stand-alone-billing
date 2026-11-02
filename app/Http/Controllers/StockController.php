<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsForDrawer;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\FinancialYearService;
use App\Services\ProductCatalog;
use App\Services\StockService;
use App\Support\StockQty;
use App\Support\WorkContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockController extends Controller
{
    use RespondsForDrawer;

    /** Stock list for a godown with batch detail; ?low=1 shows only low-stock alerts. */
    public function index(Request $request)
    {
        $godowns = $request->user()->accessibleGodowns();
        $gid = $this->godownFilter($request, $godowns->pluck('id')->all());

        // On-hand from stock_movements (single stock log), not a qty column on the product.
        $onHand = StockQty::productOnHandSql(null, true);

        $rows = Product::with(['category', 'unit', 'batches' => fn ($q) => $q->where('godown_id', $gid)->where('qty_on_hand', '>', 0)->orderBy('expiry_date')])
            ->select('products.*')
            ->selectRaw("$onHand AS on_hand", [$gid])
            ->where('is_active', true)
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = '%'.$request->q.'%';
                $q->where(fn ($w) => $w->where('name', 'like', $t)->orWhere('name_mr', 'like', $t)->orWhere('code', 'like', $t));
            })
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->category_id))
            ->when($request->boolean('low'), fn ($q) => $q->where('reorder_level', '>', 0)->whereRaw("$onHand <= products.reorder_level", [$gid]))
            ->orderBy('name')->paginate(20)->withQueryString();

        $lowCount = Product::where('is_active', true)->where('reorder_level', '>', 0)
            ->whereRaw("$onHand <= products.reorder_level", [$gid])->count();

        return view('stock.index', [
            'rows' => $rows,
            'godowns' => $godowns,
            'gid' => $gid,
            'categories' => Category::orderBy('name')->get(),
            'lowCount' => $lowCount,
        ]);
    }

    public function low(Request $request): RedirectResponse
    {
        return redirect()->route('stock.index', array_filter(['low' => 1, 'godown_id' => $request->godown_id]));
    }

    /** Large-type stock preview for counter / TV (staff login). */
    public function preview(Request $request)
    {
        $godowns = $request->user()->accessibleGodowns();
        $gid = $this->godownFilter($request, $godowns->pluck('id')->all());
        $balances = StockQty::productBalances($gid);

        $products = Product::with(['unit', 'category'])
            ->where('is_active', true)
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = '%'.$request->q.'%';
                $q->where(fn ($w) => $w->where('name', 'like', $t)->orWhere('name_mr', 'like', $t)->orWhere('code', 'like', $t));
            })
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->category_id))
            ->when($request->boolean('instock'), fn ($q) => $q->whereIn('id', array_keys(array_filter($balances, fn ($v) => $v > 0.0005))))
            ->orderBy('name')
            ->get();

        $items = $products->map(function (Product $p) use ($balances) {
            $qty = round((float) ($balances[$p->id] ?? 0), 3);

            return (object) [
                'name' => $p->display_name,
                'code' => $p->code,
                'category' => $p->category?->display_name,
                'qty' => $qty,
                'unit' => $p->unit?->short_name,
                'low' => $p->reorder_level > 0 && $qty <= (float) $p->reorder_level,
                'rate' => (float) $p->sale_rate,
            ];
        });

        return view('stock.preview', [
            'items' => $items,
            'godowns' => $godowns,
            'gid' => $gid,
            'categories' => Category::orderBy('name')->get(),
            'godownName' => $godowns->firstWhere('id', $gid)?->display_name,
        ]);
    }

    public function movements(Request $request, Product $product)
    {
        $godowns = $request->user()->accessibleGodowns();
        $gid = $this->godownFilter($request, $godowns->pluck('id')->all());

        $rows = StockMovement::with(['batch', 'godown'])
            ->where('product_id', $product->id)->where('godown_id', $gid)
            ->orderByDesc('movement_date')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('stock.movements', compact('product', 'rows', 'godowns', 'gid'));
    }

    // ---- Opening stock ------------------------------------------------

    public function openingIndex(Request $request)
    {
        $godownIds = $request->user()->accessibleGodownIds();
        $rows = StockMovement::with(['product', 'batch', 'godown'])
            ->where('movement_type', 'opening')->whereIn('godown_id', $godownIds)
            ->when($request->filled('q'), fn ($q) => $q->whereHas('product', fn ($p) => $p->where('name', 'like', '%'.$request->q.'%')))
            ->orderByDesc('id')->paginate(20)->withQueryString();

        return view('stock.opening-index', compact('rows'));
    }

    /** Bulk sheet: all items — enter qty to post opening stock (go-live / updates). */
    public function openingBulk(Request $request, ProductCatalog $catalog)
    {
        $placeId = WorkContext::placeId($request);
        $request->session()->put('godown_id', $placeId);

        return view('stock.opening-bulk', [
            'products' => $catalog->forPurchase(),
            'places' => $request->user()->accessibleGodowns(),
            'placeId' => $placeId,
            'voucherDate' => old('movement_date', app(FinancialYearService::class)->defaultVoucherDate()),
        ]);
    }

    /** Side drawer: add one item. Full-page create redirects to bulk. */
    public function openingCreate(Request $request, ProductCatalog $catalog)
    {
        if (! $this->wantsDrawer($request)) {
            return redirect()->route('opening-stock.bulk', $request->only('godown_id'));
        }

        return view('stock.opening-drawer', [
            'products' => $catalog->forPurchase(),
            'places' => $request->user()->accessibleGodowns(),
            'placeId' => WorkContext::placeId($request),
            'voucherDate' => old('movement_date', app(FinancialYearService::class)->defaultVoucherDate()),
        ]);
    }

    public function openingStore(Request $request, StockService $stock): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'movement_date' => ['required', 'date'],
            'godown_id' => ['nullable', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.batch_no' => ['nullable', 'string', 'max:60'],
            'items.*.mfg_date' => ['nullable', 'date'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.mrp' => ['nullable', 'numeric', 'min:0'],
        ]);
        $this->assertActiveFyDate($data['movement_date'], 'movement_date');

        $godownId = (int) ($data['godown_id'] ?? WorkContext::placeId($request));
        abort_unless(in_array($godownId, $request->user()->accessibleGodownIds(), true), 403);
        $request->session()->put('godown_id', $godownId);

        $lines = collect($data['items'])
            ->map(function ($row) {
                $row['qty'] = round((float) ($row['qty'] ?? 0), 3);

                return $row;
            })
            ->filter(fn ($row) => ($row['qty'] ?? 0) > 0.0005)
            ->values()
            ->all();

        if ($lines === []) {
            throw ValidationException::withMessages([
                'items' => __('Enter quantity on at least one item.'),
            ]);
        }

        try {
            $count = DB::transaction(function () use ($lines, $data, $godownId, $stock) {
                $n = 0;
                foreach ($lines as $row) {
                    $product = Product::findOrFail($row['product_id']);
                    if ($product->batch_required && blank($row['batch_no'] ?? null)) {
                        throw ValidationException::withMessages([
                            'items' => __('Batch number is required for :product.', ['product' => $product->display_name]),
                        ]);
                    }
                    $stock->receive([
                        'product_id' => $product->id,
                        'godown_id' => $godownId,
                        'batch_no' => $row['batch_no'] ?? null,
                        'mfg_date' => $row['mfg_date'] ?? null,
                        'expiry_date' => $row['expiry_date'] ?? null,
                        'qty' => (float) $row['qty'],
                        'rate' => (float) ($row['rate'] ?? $product->purchase_rate ?? 0),
                        'mrp' => (float) ($row['mrp'] ?? $product->mrp ?? 0),
                        'type' => 'opening',
                        'date' => $data['movement_date'],
                        'narration' => __('Opening stock'),
                    ]);
                    $n++;
                }

                return $n;
            });
        } catch (ValidationException $e) {
            throw $e;
        }

        $message = __('Opening stock saved (:n line(s)).', ['n' => $count]);

        return $this->savedResponse($request, $message, 'opening-stock.index');
    }

    private function godownFilter(Request $request, array $allowed): int
    {
        $gid = (int) ($request->input('godown_id') ?: $request->session()->get('godown_id'));

        return in_array($gid, $allowed, true) ? $gid : (int) ($allowed[0] ?? 0);
    }
}
