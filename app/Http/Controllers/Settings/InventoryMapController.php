<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\InventoryMap;
use App\Models\Ledger;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InventoryMapController extends Controller
{
    public function index()
    {
        return view('settings.inventory-maps', [
            'maps' => InventoryMap::with(['category', 'product', 'salesLedger', 'purchaseLedger'])->orderBy('scope')->orderBy('id')->get(),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'name_mr']),
            'salesLedgers' => Ledger::books()->where('type', 'sales')->where('is_active', true)->orderBy('name')->get(),
            'purchaseLedgers' => Ledger::books()->where('type', 'purchase')->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /** Create or update the override for a category/product. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'scope' => ['required', 'in:category,product'],
            'category_id' => ['required_if:scope,category', 'nullable', 'exists:categories,id'],
            'product_id' => ['required_if:scope,product', 'nullable', 'exists:products,id'],
            'sales_ledger_id' => ['nullable', 'exists:ledgers,id'],
            'purchase_ledger_id' => ['nullable', 'exists:ledgers,id'],
        ]);

        if (blank($data['sales_ledger_id'] ?? null) && blank($data['purchase_ledger_id'] ?? null)) {
            return back()->withInput()->withErrors(['sales_ledger_id' => __('Choose at least one ledger override.')]);
        }

        $key = $data['scope'] === 'category'
            ? ['scope' => 'category', 'category_id' => $data['category_id'], 'product_id' => null]
            : ['scope' => 'product', 'product_id' => $data['product_id'], 'category_id' => null];

        InventoryMap::updateOrCreate($key, [
            'sales_ledger_id' => $data['sales_ledger_id'] ?? null,
            'purchase_ledger_id' => $data['purchase_ledger_id'] ?? null,
        ]);

        return back()->with('status', __('Inventory map saved.'));
    }

    public function destroy(InventoryMap $inventoryMap): RedirectResponse
    {
        $inventoryMap->delete();

        return back()->with('status', __('Inventory map removed.'));
    }
}
