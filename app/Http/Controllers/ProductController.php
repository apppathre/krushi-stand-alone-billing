<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsForDrawer;
use App\Models\Category;
use App\Models\GstSlab;
use App\Models\HsnCode;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    use RespondsForDrawer;

    public function index(Request $request)
    {
        $rows = Product::with(['category', 'manufacturer', 'unit'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = '%'.$request->q.'%';
                $q->where(fn ($w) => $w->where('name', 'like', $t)->orWhere('name_mr', 'like', $t)
                    ->orWhere('code', 'like', $t)->orWhere('hsn_code', 'like', $t)
                    ->orWhere('packing', 'like', $t)->orWhere('marketed_by', 'like', $t));
            })
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->category_id))
            ->when($request->filled('manufacturer_id'), fn ($q) => $q->where('manufacturer_id', $request->manufacturer_id))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'))
            ->orderBy('name')->paginate(15)->withQueryString();

        return view('products.index', [
            'rows' => $rows,
            'categories' => Category::orderBy('name')->get(),
            'manufacturers' => Manufacturer::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('products._drawer', $this->formData(new Product([
            'is_active' => true, 'batch_required' => true, 'show_on_live_stock' => true, 'gst_rate' => 5,
        ])));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $product = Product::create($this->payload($request));
        if (blank($product->code)) {
            $product->update(['code' => 'P'.str_pad((string) $product->id, 5, '0', STR_PAD_LEFT)]);
        }

        return $this->savedResponse($request, __('Product saved.'), 'products.index');
    }

    /** Excel-style multi-row item entry. */
    public function bulkCreate()
    {
        return view('products.bulk', [
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
            'manufacturers' => Manufacturer::where('is_active', true)->orderBy('name')->get(),
            'units' => Unit::where('is_active', true)->orderBy('name')->get(),
            'hsnCodes' => HsnCode::where('is_active', true)->orderBy('code')->get(),
            'gstSlabs' => GstSlab::where('is_active', true)->orderBy('sort_order')->orderBy('rate')->pluck('rate')->map(fn ($r) => qty($r))->all()
                ?: ['0', '5', '12', '18', '28'],
        ]);
    }

    public function bulkStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.code' => ['nullable', 'string', 'max:40'],
            'rows.*.name' => ['nullable', 'string', 'max:255'],
            'rows.*.name_mr' => ['nullable', 'string', 'max:255'],
            'rows.*.category_id' => ['nullable', 'exists:categories,id'],
            'rows.*.manufacturer_code' => ['nullable', 'string', 'max:20'],
            'rows.*.manufacturer_id' => ['nullable', 'exists:manufacturers,id'],
            'rows.*.unit_id' => ['nullable', 'exists:units,id'],
            'rows.*.packing' => ['nullable', 'string', 'max:120'],
            'rows.*.marketed_by' => ['nullable', 'string', 'max:150'],
            'rows.*.usage' => ['nullable', 'string', 'max:255'],
            'rows.*.hsn_code' => ['nullable', 'string', 'max:12'],
            'rows.*.gst_rate' => ['nullable', 'numeric', 'between:0,100'],
            'rows.*.purchase_rate' => ['nullable', 'numeric', 'min:0'],
            'rows.*.sale_rate' => ['nullable', 'numeric', 'min:0'],
            'rows.*.mrp' => ['nullable', 'numeric', 'min:0'],
            'rows.*.reorder_level' => ['nullable', 'numeric', 'min:0'],
            'rows.*.batch_required' => ['nullable'],
        ]);

        $mfrByCode = Manufacturer::whereNotNull('short_code')->get()->keyBy(fn ($m) => strtoupper($m->short_code));
        $saved = 0;
        $errors = [];

        DB::transaction(function () use ($data, $mfrByCode, &$saved, &$errors) {
            foreach ($data['rows'] as $i => $row) {
                if (blank($row['name'] ?? null)) {
                    continue; // skip empty Excel rows
                }
                $line = $i + 1;
                if (blank($row['category_id'] ?? null) || blank($row['unit_id'] ?? null)) {
                    $errors["rows.$i.name"] = __('Row :n needs category and unit.', ['n' => $line]);

                    continue;
                }
                if (! isset($row['gst_rate']) || $row['gst_rate'] === '') {
                    $errors["rows.$i.gst_rate"] = __('Row :n needs GST %.', ['n' => $line]);

                    continue;
                }

                $mfrId = $row['manufacturer_id'] ?? null;
                if (! $mfrId && filled($row['manufacturer_code'] ?? null)) {
                    $code = strtoupper(trim($row['manufacturer_code']));
                    $mfrId = $mfrByCode[$code]->id ?? null;
                    if (! $mfrId) {
                        $errors["rows.$i.manufacturer_code"] = __('Row :n: unknown manufacturer short code :code.', ['n' => $line, 'code' => $code]);

                        continue;
                    }
                }

                $code = filled($row['code'] ?? null) ? trim($row['code']) : null;
                if ($code && Product::where('code', $code)->exists()) {
                    $errors["rows.$i.code"] = __('Row :n: product code already exists.', ['n' => $line]);

                    continue;
                }

                $product = Product::create([
                    'code' => $code,
                    'name' => trim($row['name']),
                    'name_mr' => $row['name_mr'] ?? null,
                    'category_id' => $row['category_id'],
                    'manufacturer_id' => $mfrId,
                    'unit_id' => $row['unit_id'],
                    'packing' => $row['packing'] ?? null,
                    'marketed_by' => $row['marketed_by'] ?? null,
                    'usage' => $row['usage'] ?? null,
                    'hsn_code' => $row['hsn_code'] ?? null,
                    'gst_rate' => (float) $row['gst_rate'],
                    'purchase_rate' => (float) ($row['purchase_rate'] ?? 0),
                    'sale_rate' => (float) ($row['sale_rate'] ?? 0),
                    'mrp' => (float) ($row['mrp'] ?? 0),
                    'reorder_level' => (float) ($row['reorder_level'] ?? 0),
                    'batch_required' => filter_var($row['batch_required'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'is_active' => true,
                ]);
                if (blank($product->code)) {
                    $product->update(['code' => 'P'.str_pad((string) $product->id, 5, '0', STR_PAD_LEFT)]);
                }
                $saved++;
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
            if ($saved === 0) {
                throw ValidationException::withMessages(['rows' => __('Add at least one row with a product name.')]);
            }
        });

        return redirect()->route('products.index')
            ->with('status', __(':count items saved.', ['count' => $saved]));
    }

    public function edit(Product $product)
    {
        return view('products._drawer', $this->formData($product));
    }

    public function update(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        $before = $product->only(['sale_rate', 'mrp', 'purchase_rate']);
        $payload = $this->payload($request, $product->id);
        $product->update($payload);
        app(\App\Services\ProductPriceLogger::class)->logChanges(
            $product,
            $before,
            $payload,
            $request->user()?->id,
            __('Product edit')
        );

        return $this->savedResponse($request, __('Product saved.'), 'products.index');
    }

    /** Side drawer: pick which items appear on the live stock TV board. */
    public function liveBoardPicker()
    {
        $items = Product::query()
            ->with('category:id,name,name_mr')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'name_mr', 'category_id', 'show_on_live_stock'])
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->display_name,
                'category_id' => $p->category_id,
                'category' => $p->category?->display_name,
                'show' => (bool) $p->show_on_live_stock,
            ])
            ->values()
            ->all();

        return view('products._live-board-drawer', [
            'items' => $items,
            'categories' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name', 'name_mr']),
        ]);
    }

    /** Save live-board visibility for all active items in one go. */
    public function liveBoardSync(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'show_ids' => ['nullable', 'array'],
            'show_ids.*' => ['integer', 'exists:products,id'],
            'sync_all' => ['required', 'in:1'],
        ]);

        $showIds = array_values(array_unique(array_map('intval', $data['show_ids'] ?? [])));

        DB::transaction(function () use ($showIds) {
            // Only touch active items — inactive keep their flag for when reactivated.
            Product::query()->where('is_active', true)->update(['show_on_live_stock' => false]);
            if ($showIds !== []) {
                Product::query()
                    ->where('is_active', true)
                    ->whereIn('id', $showIds)
                    ->update(['show_on_live_stock' => true]);
            }
        });

        $on = count($showIds);

        return $this->savedResponse(
            $request,
            __('Live screen items saved. :n item(s) will show on the board.', ['n' => $on]),
            'products.index'
        );
    }

    public function destroy(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        try {
            $product->delete();
        } catch (QueryException) {
            return $this->failedResponse($request, __('This record is in use and cannot be deleted. Deactivate it instead.'));
        }

        return $this->savedResponse($request, __('Product deleted.'), 'products.index');
    }

    private function formData(Product $product): array
    {
        $gstSlabs = GstSlab::where('is_active', true)->orderBy('sort_order')->orderBy('rate')->get();
        if ($gstSlabs->isEmpty()) {
            $gstSlabs = collect([0, 5, 12, 18, 28])->map(fn ($r) => (object) [
                'rate' => $r, 'display_name' => 'GST '.$r.'%',
            ]);
        }

        return [
            'product' => $product,
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
            'manufacturers' => Manufacturer::where('is_active', true)->orderBy('name')->get(),
            'units' => Unit::where('is_active', true)->orderBy('name')->get(),
            'hsnCodes' => HsnCode::where('is_active', true)->orderBy('code')->get(),
            'gstSlabs' => $gstSlabs,
        ];
    }

    private function payload(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40', 'unique:products,code'.($id ? ','.$id : '')],
            'name' => ['required', 'string', 'max:255'],
            'name_mr' => ['nullable', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'manufacturer_id' => ['nullable', 'exists:manufacturers,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'packing' => ['nullable', 'string', 'max:120'],
            'marketed_by' => ['nullable', 'string', 'max:150'],
            'usage' => ['nullable', 'string', 'max:255'],
            'hsn_code' => ['nullable', 'string', 'max:12'],
            'gst_rate' => ['required', 'numeric', 'between:0,100'],
            'purchase_rate' => ['nullable', 'numeric', 'min:0'],
            'sale_rate' => ['nullable', 'numeric', 'min:0'],
            'mrp' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
        ]);

        foreach (['purchase_rate', 'sale_rate', 'mrp', 'reorder_level'] as $k) {
            $data[$k] = $data[$k] ?? 0;
        }
        $data['batch_required'] = $request->boolean('batch_required');
        $data['is_active'] = $request->boolean('is_active');
        $data['show_on_live_stock'] = $request->boolean('show_on_live_stock');

        return $data;
    }
}
