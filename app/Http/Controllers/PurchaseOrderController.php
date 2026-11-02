<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\GstSlab;
use App\Models\Ledger;
use App\Models\PurchaseOrder;
use App\Services\FinancialYearService;
use App\Services\InvoiceNumberGenerator;
use App\Services\ProductCatalog;
use App\Services\PurchaseOrderService;
use App\Support\IndianStates;
use App\Support\TermsHtml;
use App\Support\WorkContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private PurchaseOrderService $orders,
        private InvoiceNumberGenerator $numbers,
    ) {}

    public function index(Request $request, ProductCatalog $catalog)
    {
        $placeId = WorkContext::placeId($request);
        $voucherDate = old('order_date', app(FinancialYearService::class)->defaultVoucherDate());
        $places = $request->user()->accessibleGodowns();

        return view('purchase-orders.index', [
            'suppliers' => Ledger::suppliers()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'name_mr', 'state_code', 'phone']),
            'products' => $catalog->forPurchase(),
            'racksByPlace' => $catalog->racksByPlace($request->user()->accessibleGodownIds()),
            'states' => IndianStates::options(),
            'companyState' => CompanyProfile::current()->state_code,
            'places' => $places,
            'placeId' => $placeId,
            'gstRates' => GstSlab::where('is_active', true)->orderBy('sort_order')->orderBy('rate')->pluck('rate')
                ->map(fn ($r) => (float) $r)->unique()->values()->all() ?: [0, 5, 12, 18, 28],
            'numberFormats' => $this->numbers->formatsFor('purchase_order', $voucherDate),
            'defaultTerms' => TermsHtml::fromCompany(CompanyProfile::current()->invoice_terms),
            'voucherDate' => $voucherDate,
            'editing' => null,
            'editItems' => [],
        ]);
    }

    public function browse(Request $request)
    {
        [$from, $to] = $this->listDateRange($request);
        $rows = PurchaseOrder::with(['supplier', 'godown'])
            ->whereIn('godown_id', $this->godownScope($request))
            ->whereDate('order_date', '>=', $from)->whereDate('order_date', '<=', $to)
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = '%'.$request->q.'%';
                $q->where(fn ($w) => $w->where('order_no', 'like', $t)
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $t)));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('order_date')->orderByDesc('id')
            ->paginate(15)->withQueryString();

        if ($request->ajax() || $request->boolean('list')) {
            return view('purchase-orders._list', compact('rows'));
        }

        return view('purchase-orders._browse', [
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'godowns' => $request->user()->accessibleGodowns(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canMenu('purchase_orders', 'add'), 403);
        $data = $this->validated($request);
        $this->assertActiveFyDate($data['order_date'], 'order_date');
        $this->authorizeGodown($request, (int) $data['godown_id']);
        $request->session()->put('godown_id', (int) $data['godown_id']);

        try {
            $order = $this->orders->create($data, $request->user()->id);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('purchase-orders.show', $order)
            ->with('status', __('Purchase order :no saved.', ['no' => $order->order_no]));
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorizeGodown($request, $purchaseOrder->godown_id);
        $purchaseOrder->load(['items.product.unit', 'supplier', 'godown', 'convertedPurchase', 'user']);

        return view('purchase-orders.show', ['order' => $purchaseOrder]);
    }

    public function edit(Request $request, PurchaseOrder $purchaseOrder, ProductCatalog $catalog)
    {
        $this->authorizeGodown($request, $purchaseOrder->godown_id);
        abort_unless($purchaseOrder->isOpen(), 404);
        abort_unless($request->user()->canMenu('purchase_orders', 'edit'), 403);

        $placeId = $purchaseOrder->godown_id;
        $voucherDate = old('order_date', $purchaseOrder->order_date->toDateString());
        $places = $request->user()->accessibleGodowns();

        return view('purchase-orders.index', [
            'suppliers' => Ledger::suppliers()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'name_mr', 'state_code', 'phone']),
            'products' => $catalog->forPurchase(),
            'racksByPlace' => $catalog->racksByPlace($request->user()->accessibleGodownIds()),
            'states' => IndianStates::options(),
            'companyState' => CompanyProfile::current()->state_code,
            'places' => $places,
            'placeId' => old('godown_id', $placeId),
            'gstRates' => GstSlab::where('is_active', true)->orderBy('sort_order')->orderBy('rate')->pluck('rate')
                ->map(fn ($r) => (float) $r)->unique()->values()->all() ?: [0, 5, 12, 18, 28],
            'numberFormats' => $this->numbers->formatsFor('purchase_order', $voucherDate),
            'defaultTerms' => $purchaseOrder->terms ?: TermsHtml::fromCompany(CompanyProfile::current()->invoice_terms),
            'voucherDate' => $voucherDate,
            'editing' => $purchaseOrder,
            'editItems' => $purchaseOrder->items->map(fn ($i) => [
                'godown_id' => (string) ($i->godown_id ?: $purchaseOrder->godown_id),
                'product_id' => (string) $i->product_id,
                'qty' => (string) $i->qty,
                'rate' => (float) $i->rate,
                'mrp' => (float) $i->mrp,
                'discount_pct' => (float) $i->discount_pct,
                'gst_rate' => (float) $i->gst_rate,
            ])->all(),
        ]);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorizeGodown($request, $purchaseOrder->godown_id);
        abort_unless($request->user()->canMenu('purchase_orders', 'edit'), 403);
        $data = $this->validated($request);
        $this->assertActiveFyDate($data['order_date'], 'order_date');
        $this->authorizeGodown($request, (int) $data['godown_id']);

        try {
            $order = $this->orders->update($purchaseOrder, $data, $request->user()->id);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('purchase-orders.show', $order)
            ->with('status', __('Purchase order :no updated.', ['no' => $order->order_no]));
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorizeGodown($request, $purchaseOrder->godown_id);
        abort_unless($request->user()->canMenu('purchase_orders', 'delete'), 403);
        try {
            $this->orders->cancel($purchaseOrder);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', __('Purchase order cancelled.'));
    }

    /** Prefill purchase desk from this PO. */
    public function convert(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorizeGodown($request, $purchaseOrder->godown_id);
        abort_unless($purchaseOrder->isOpen(), 404);
        abort_unless($request->user()->canMenu('purchases', 'add'), 403);

        return redirect()->route('purchases.index', [
            'purchase_order_id' => $purchaseOrder->id,
            'godown_id' => $purchaseOrder->godown_id,
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date'],
            'document_series_id' => ['nullable', 'integer', 'exists:document_series,id'],
            'godown_id' => ['required', 'integer'],
            'supplier_ledger_id' => ['required', 'exists:ledgers,id'],
            'supply_state_code' => ['nullable', 'in:'.implode(',', array_keys(IndianStates::LIST))],
            'notes' => ['nullable', 'string', 'max:500'],
            'terms' => ['nullable', 'string', 'max:20000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.godown_id' => ['nullable', 'integer'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.mrp' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_pct' => ['nullable', 'numeric', 'between:0,100'],
            'items.*.gst_rate' => ['nullable', 'numeric', 'between:0,100'],
        ]);
    }
}
