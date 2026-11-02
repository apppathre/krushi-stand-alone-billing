<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\GstSlab;
use App\Models\Ledger;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Support\TermsHtml;
use App\Services\FinancialYearService;
use App\Services\InvoiceNumberGenerator;
use App\Services\ProductCatalog;
use App\Services\PurchaseService;
use App\Support\IndianStates;
use App\Support\WorkContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{
    public function __construct(
        private PurchaseService $purchases,
        private InvoiceNumberGenerator $numbers,
    ) {}

    /** Purchase desk: entry form only — recent list opens in a wide drawer. */
    public function index(Request $request, ProductCatalog $catalog)
    {
        $placeId = WorkContext::placeId($request);
        $voucherDate = old('purchase_date', app(FinancialYearService::class)->defaultVoucherDate());
        $places = $request->user()->accessibleGodowns();

        $fromOrder = null;
        $prefillItems = old('items', []);
        $prefillSupplier = old('supplier_ledger_id', '');
        $prefillState = old('supply_state_code');
        $settlement = old('settlement_type', 'credit');
        $paid = (float) old('paid_amount', 0);

        if ($request->filled('purchase_order_id') && $prefillItems === []) {
            $fromOrder = PurchaseOrder::with('items')->find((int) $request->purchase_order_id);
            if ($fromOrder && $fromOrder->isOpen()) {
                $this->authorizeGodown($request, $fromOrder->godown_id);
                $placeId = $fromOrder->godown_id;
                $prefillSupplier = (string) $fromOrder->supplier_ledger_id;
                $prefillState = $fromOrder->supply_state_code;
                $prefillItems = $fromOrder->items->map(fn ($i) => [
                    'godown_id' => (string) ($i->godown_id ?: $fromOrder->godown_id),
                    'product_id' => (string) $i->product_id,
                    'qty' => (string) $i->qty,
                    'rate' => (float) $i->rate,
                    'mrp' => (float) $i->mrp,
                    'discount_pct' => (float) $i->discount_pct,
                    'gst_rate' => (float) $i->gst_rate,
                    'batch_no' => '',
                    'mfg_date' => '',
                    'expiry_date' => '',
                ])->all();
            } else {
                $fromOrder = null;
            }
        }

        return view('purchases.index', [
            'suppliers' => Ledger::suppliers()->where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'name_mr', 'state_code', 'phone', 'credit_days']),
            'products' => $catalog->forPurchase(),
            'racksByPlace' => $catalog->racksByPlace($request->user()->accessibleGodownIds()),
            'states' => IndianStates::options(),
            'companyState' => CompanyProfile::current()->state_code,
            'defaultCreditDays' => (int) (CompanyProfile::current()->default_credit_days ?? 30),
            'places' => $places,
            'placeId' => $placeId,
            'gstRates' => GstSlab::where('is_active', true)->orderBy('sort_order')->orderBy('rate')->pluck('rate')
                ->map(fn ($r) => (float) $r)->unique()->values()->all() ?: [0, 5, 12, 18, 28],
            'numberFormats' => $this->numbers->formatsFor('purchase', $voucherDate),
            'defaultTerms' => TermsHtml::fromCompany(CompanyProfile::current()->invoice_terms),
            'voucherDate' => $voucherDate,
            'fromOrder' => $fromOrder,
            'prefillItems' => $prefillItems,
            'prefillSupplier' => $prefillSupplier,
            'prefillState' => $prefillState ?: CompanyProfile::current()->state_code,
            'settlement' => $settlement,
            'paid' => $paid,
        ]);
    }

    /** Wide drawer / AJAX browser for recent purchases. */
    public function browse(Request $request)
    {
        [$from, $to] = $this->listDateRange($request);
        $rows = $this->purchaseQuery($request, $from, $to)->paginate(15)->withQueryString();

        if ($request->ajax() || $request->boolean('list')) {
            return view('purchases._list', compact('rows'));
        }

        return view('purchases._browse', [
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'filterSuppliers' => Ledger::suppliers()->orderBy('name')->get(['id', 'name', 'name_mr']),
            'godowns' => $request->user()->accessibleGodowns(),
        ]);
    }

    private function purchaseQuery(Request $request, string $from, string $to)
    {
        return Purchase::with(['supplier', 'godown'])
            ->whereIn('godown_id', $this->godownScope($request))
            ->whereDate('purchase_date', '>=', $from)->whereDate('purchase_date', '<=', $to)
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = '%'.$request->q.'%';
                $q->where(fn ($w) => $w->where('purchase_no', 'like', $t)->orWhere('supplier_invoice_no', 'like', $t)
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $t)));
            })
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_ledger_id', $request->supplier_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('purchase_date')->orderByDesc('id');
    }

    public function create(Request $request)
    {
        return redirect()->route('purchases.index', $request->only('godown_id'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'purchase_date' => ['required', 'date'],
            'document_series_id' => ['nullable', 'integer', 'exists:document_series,id'],
            'godown_id' => ['required', 'integer'],
            'supplier_ledger_id' => ['required', 'exists:ledgers,id'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:100'],
            'supplier_invoice_date' => ['nullable', 'date'],
            'supply_state_code' => ['nullable', 'in:'.implode(',', array_keys(IndianStates::LIST))],
            'notes' => ['nullable', 'string', 'max:500'],
            'terms' => ['nullable', 'string', 'max:20000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.godown_id' => ['nullable', 'integer'],
            'items.*.rack_id' => ['nullable', 'integer', 'exists:racks,id'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.batch_no' => ['nullable', 'string', 'max:60'],
            'items.*.mfg_date' => ['nullable', 'date'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.mrp' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_pct' => ['nullable', 'numeric', 'between:0,100'],
            'items.*.gst_rate' => ['nullable', 'numeric', 'between:0,100'],
            'settlement_type' => ['nullable', 'in:credit,cash'],
            'credit_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'cash_discount' => ['nullable', 'numeric', 'min:0'],
            'freight_amount' => ['nullable', 'numeric', 'min:0'],
            'hammali_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_ledger_id' => Ledger::paymentInstrumentRule(false),
            'payment_mode' => ['nullable', 'in:cash,bank,upi'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
        ]);

        // Infer settlement when older clients omit it: paid > 0 ⇒ cash, else credit.
        $settlement = $data['settlement_type']
            ?? (((float) ($data['paid_amount'] ?? 0) > 0) ? 'cash' : 'credit');
        $data['settlement_type'] = $settlement;

        if ($settlement === 'credit') {
            $data['paid_amount'] = 0;
        } else {
            if ((float) ($data['paid_amount'] ?? 0) <= 0) {
                throw ValidationException::withMessages([
                    'paid_amount' => __('Enter how much you paid now, or switch to Credit.'),
                ]);
            }
            if (blank($data['payment_ledger_id'] ?? null)) {
                throw ValidationException::withMessages([
                    'payment_ledger_id' => __('Select how you paid (Cash / Bank / UPI).'),
                ]);
            }
        }

        $this->assertActiveFyDate($data['purchase_date'], 'purchase_date');
        $godownId = (int) $data['godown_id'];
        $this->authorizeGodown($request, $godownId);
        foreach ($data['items'] as $row) {
            $this->authorizeGodown($request, (int) ($row['godown_id'] ?? $godownId));
        }
        $request->session()->put('godown_id', $godownId);

        try {
            $purchase = $this->purchases->create($data + ['godown_id' => $godownId], $request->user()->id);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['items' => $e->getMessage()]);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('purchases.show', $purchase)->with('status', __('Purchase :no recorded.', ['no' => $purchase->purchase_no]));
    }

    public function show(Request $request, Purchase $purchase)
    {
        $this->authorizeGodown($request, $purchase->godown_id);
        $purchase->load(['items.product.unit', 'supplier', 'godown', 'payments.ledger']);

        return view('purchases.show', compact('purchase'));
    }

    /** Full line / tax / terms view — summary stays on show. */
    public function details(Request $request, Purchase $purchase)
    {
        $this->authorizeGodown($request, $purchase->godown_id);
        $purchase->load(['items.product.unit', 'supplier', 'godown', 'payments.ledger']);

        return view('purchases.details', compact('purchase'));
    }

    public function payment(Request $request, Purchase $purchase): RedirectResponse
    {
        $this->authorizeGodown($request, $purchase->godown_id);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'ledger_id' => Ledger::paymentInstrumentRule(true, 'mode'),
            'mode' => ['required_without:ledger_id', 'nullable', 'in:cash,bank,upi'],
            'payment_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);
        $this->assertActiveFyDate($data['payment_date'], 'payment_date');

        try {
            $this->purchases->addPayment($purchase, $data);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', __('Payment recorded.'));
    }

    public function cancel(Request $request, Purchase $purchase): RedirectResponse
    {
        $this->authorizeGodown($request, $purchase->godown_id);
        $this->purchases->cancel($purchase);

        return redirect()->route('purchases.show', $purchase)->with('status', __('Purchase cancelled.'));
    }
}
