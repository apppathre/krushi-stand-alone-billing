<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductPriceLog;
use App\Services\ProductPriceLogger;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Price list browse / manage / change log. */
class PriceListController extends Controller
{
    public function __construct(
        private ReportService $reports,
        private ProductPriceLogger $logger,
    ) {}

    public function index(Request $request)
    {
        $tab = $request->input('tab', 'browse');
        if (! in_array($tab, ['browse', 'manage', 'log'], true)) {
            $tab = 'browse';
        }

        $categoryId = $request->filled('category_id') ? (int) $request->category_id : null;
        $manufacturerId = $request->filled('manufacturer_id') ? (int) $request->manufacturer_id : null;
        $categories = Category::orderBy('name')->get();
        $manufacturers = Manufacturer::orderBy('name')->get();

        if ($tab === 'log') {
            abort_unless($request->user()->canMenu('report_price_list', 'view'), 403);
            $logs = ProductPriceLog::with(['product.unit', 'user'])
                ->when($request->filled('q'), function ($q) use ($request) {
                    $t = '%'.$request->q.'%';
                    $q->whereHas('product', fn ($p) => $p->where('name', 'like', $t)->orWhere('code', 'like', $t));
                })
                ->orderByDesc('id')
                ->paginate(40)
                ->withQueryString();

            return view('reports.price-list', compact('tab', 'logs', 'categories', 'manufacturers', 'categoryId', 'manufacturerId') + [
                'rows' => collect(),
            ]);
        }

        if ($tab === 'manage') {
            abort_unless($request->user()->canMenu('products', 'edit') || $request->user()->canMenu('report_price_list', 'edit'), 403);
            $products = Product::with(['unit', 'category', 'manufacturer'])
                ->where('is_active', true)
                ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
                ->when($manufacturerId, fn ($q) => $q->where('manufacturer_id', $manufacturerId))
                ->when($request->filled('q'), function ($q) use ($request) {
                    $t = '%'.$request->q.'%';
                    $q->where(fn ($w) => $w->where('name', 'like', $t)->orWhere('name_mr', 'like', $t)->orWhere('code', 'like', $t));
                })
                ->orderBy('name')
                ->paginate(40)
                ->withQueryString();

            return view('reports.price-list', [
                'tab' => 'manage',
                'products' => $products,
                'categories' => $categories,
                'manufacturers' => $manufacturers,
                'categoryId' => $categoryId,
                'manufacturerId' => $manufacturerId,
                'rows' => collect(),
                'logs' => null,
            ]);
        }

        $data = $this->reports->priceList($categoryId, $manufacturerId);

        return view('reports.price-list', $data + [
            'tab' => 'browse',
            'categoryId' => $categoryId,
            'manufacturerId' => $manufacturerId,
            'categories' => $categories,
            'manufacturers' => $manufacturers,
            'logs' => null,
            'products' => null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canMenu('products', 'edit') || $request->user()->canMenu('report_price_list', 'edit'), 403);

        $data = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.id' => ['required', 'integer', 'exists:products,id'],
            'rows.*.sale_rate' => ['nullable', 'numeric', 'min:0'],
            'rows.*.mrp' => ['nullable', 'numeric', 'min:0'],
            'rows.*.purchase_rate' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $note = $data['note'] ?? __('Price list update');
        $changed = 0;

        foreach ($data['rows'] as $row) {
            $product = Product::find($row['id']);
            if (! $product) {
                continue;
            }
            $before = $product->only(['sale_rate', 'mrp', 'purchase_rate']);
            $after = [
                'sale_rate' => (float) ($row['sale_rate'] ?? $product->sale_rate),
                'mrp' => (float) ($row['mrp'] ?? $product->mrp),
                'purchase_rate' => (float) ($row['purchase_rate'] ?? $product->purchase_rate),
            ];
            if (
                abs((float) $before['sale_rate'] - $after['sale_rate']) < 0.005
                && abs((float) $before['mrp'] - $after['mrp']) < 0.005
                && abs((float) $before['purchase_rate'] - $after['purchase_rate']) < 0.005
            ) {
                continue;
            }
            $product->update($after);
            $this->logger->logChanges($product, $before, $after, $request->user()->id, $note);
            $changed++;
        }

        return back()->with('status', __(':count prices updated.', ['count' => $changed]));
    }
}
