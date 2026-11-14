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
use App\Support\PdfMaker;
use App\Support\PrintFonts;
use App\Support\PrintFormats;
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

        return view('purchases.index', $this->deskPayload($request, $catalog, [
            'placeId' => $placeId,
            'voucherDate' => $voucherDate,
            'fromOrder' => $fromOrder,
            'prefillItems' => $prefillItems,
            'prefillSupplier' => $prefillSupplier,
            'prefillState' => $prefillState ?: CompanyProfile::current()->state_code,
            'settlement' => $settlement,
            'paid' => $paid,
            'editing' => null,
            'editItems' => [],
        ]));
    }

    /** @param  array<string, mixed>  $extra */
    private function deskPayload(Request $request, ProductCatalog $catalog, array $extra): array
    {
        $voucherDate = $extra['voucherDate'] ?? app(FinancialYearService::class)->defaultVoucherDate();

        return array_merge([
            'suppliers' => Ledger::suppliers()->where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'name_mr', 'state_code', 'phone', 'credit_days']),
            'products' => $catalog->forPurchase(),
            'racksByPlace' => $catalog->racksByPlace($request->user()->accessibleGodownIds()),
            'states' => IndianStates::options(),
            'companyState' => CompanyProfile::current()->state_code,
            'defaultCreditDays' => (int) (CompanyProfile::current()->default_credit_days ?? 30),
            'places' => $request->user()->accessibleGodowns(),
            'gstRates' => GstSlab::where('is_active', true)->orderBy('sort_order')->orderBy('rate')->pluck('rate')
                ->map(fn ($r) => (float) $r)->unique()->values()->all() ?: [0, 5, 12, 18, 28],
            'numberFormats' => $this->numbers->formatsFor('purchase', $voucherDate),
            'defaultTerms' => TermsHtml::fromCompany(CompanyProfile::current()->invoice_terms),
        ], $extra);
    }

    /** Wide drawer / AJAX browser for recent purchases. */
    public function browse(Request $request)
    {
        [$from, $to] = $this->listDateRange($request);
        $perPage = $this->listPerPage($request);
        $rows = $this->purchaseQuery($request, $from, $to)->paginate($perPage)->withQueryString();

        // Drawer open sends X-Requested-With + X-UI-Drawer — return full browse (filters).
        // autoFilter sends list=1 for the table-only partial.
        if ($request->boolean('list')) {
            return view('purchases._list', compact('rows'));
        }

        return view('purchases._browse', [
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'perPage' => $perPage,
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
            ->when(
                $request->filled('supplier_id') || $request->filled('ledger_id'),
                fn ($q) => $q->where('supplier_ledger_id', (int) ($request->input('supplier_id') ?: $request->input('ledger_id')))
            )
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('purchase_date')->orderByDesc('id');
    }

    public function create(Request $request)
    {
        return redirect()->route('purchases.index', $request->only('godown_id'));
    }

    public function edit(Request $request, Purchase $purchase, ProductCatalog $catalog)
    {
        abort_unless($request->user()->canMenu('purchases', 'edit'), 403);
        $this->authorizeGodown($request, $purchase->godown_id);
        abort_unless($purchase->isEditable(), 403, __('This purchase cannot be edited.'));

        $purchase->load(['items.product.unit', 'items.batch', 'supplier']);
        $editItems = $purchase->items->map(fn ($i) => [
            'godown_id' => (string) ($i->godown_id ?: $purchase->godown_id),
            'rack_id' => (string) ($i->rack_id ?: ''),
            'product_id' => (string) $i->product_id,
            'qty' => (string) $i->qty,
            'rate' => (float) $i->rate,
            'mrp' => (float) $i->mrp,
            'discount_pct' => (float) $i->discount_pct,
            'gst_rate' => (float) $i->gst_rate,
            'batch_no' => (string) ($i->batch_no ?: $i->batch?->batch_no ?: ''),
            'mfg_date' => optional($i->mfg_date)->toDateString() ?: '',
            'expiry_date' => optional($i->expiry_date)->toDateString() ?: '',
        ])->all();

        return view('purchases.index', $this->deskPayload($request, $catalog, [
            'placeId' => (int) $purchase->godown_id,
            'voucherDate' => $purchase->purchase_date->toDateString(),
            'fromOrder' => null,
            'prefillItems' => $editItems,
            'prefillSupplier' => (string) $purchase->supplier_ledger_id,
            'prefillState' => $purchase->supply_state_code ?: CompanyProfile::current()->state_code,
            'settlement' => $purchase->settlement_type ?: (((float) $purchase->paid_amount > 0) ? 'cash' : 'credit'),
            'paid' => (float) $purchase->paid_amount,
            'editing' => $purchase,
            'editItems' => $editItems,
            'defaultTerms' => $purchase->terms ?: TermsHtml::fromCompany(CompanyProfile::current()->invoice_terms),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedPurchase($request);
        $afterSave = $data['after_save'] ?? 'show';
        unset($data['after_save']);

        try {
            $purchase = $this->purchases->create($data, $request->user()->id);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['items' => $e->getMessage()]);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return $this->afterPurchaseSave($purchase, $afterSave);
    }

    public function update(Request $request, Purchase $purchase): RedirectResponse
    {
        abort_unless($request->user()->canMenu('purchases', 'edit'), 403);
        $this->authorizeGodown($request, $purchase->godown_id);
        abort_unless($purchase->isEditable(), 403, __('This purchase cannot be edited.'));

        $data = $this->validatedPurchase($request, forUpdate: true);
        $afterSave = $data['after_save'] ?? 'show';
        unset($data['after_save'], $data['purchase_order_id'], $data['document_series_id']);

        try {
            $purchase = $this->purchases->update($purchase, $data, $request->user()->id);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['items' => $e->getMessage()]);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return $this->afterPurchaseSave($purchase, $afterSave, updated: true);
    }

    /** @return array<string, mixed> */
    private function validatedPurchase(Request $request, bool $forUpdate = false): array
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
            'after_save' => ['nullable', 'in:show,print'],
        ]);

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
        $data['godown_id'] = $godownId;

        if ($forUpdate) {
            unset($data['purchase_order_id'], $data['document_series_id']);
        }

        return $data;
    }

    private function afterPurchaseSave(Purchase $purchase, string $afterSave, bool $updated = false): RedirectResponse
    {
        $status = $updated
            ? __('Purchase :no updated successfully.', ['no' => $purchase->purchase_no])
            : __('Purchase :no saved successfully.', ['no' => $purchase->purchase_no]);

        if ($afterSave === 'print') {
            $company = CompanyProfile::current();
            $lang = PrintFormats::defaultSaleLang($company);
            $size = PrintFormats::defaultSaleSize($company);

            return redirect()
                ->route('purchases.pdf', [$purchase, 'size' => $size, 'lang' => $lang])
                ->with('status', $status);
        }

        return redirect()->route('purchases.show', $purchase)->with('status', $status);
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

    public function pdf(Request $request, Purchase $purchase)
    {
        $this->authorizeGodown($request, $purchase->godown_id);
        $company = CompanyProfile::current();
        $size = (string) $request->input('size', PrintFormats::defaultSaleSize($company));
        if (! in_array($size, ['a4', 'half', 'quarter'], true)) {
            $size = 'a4';
        }
        $lang = (string) $request->input('lang', PrintFormats::defaultSaleLang($company));
        if (! in_array($lang, ['en', 'mr'], true)) {
            $lang = 'en';
        }

        $purchase->load([
            'items.product.unit',
            'items.batch',
            'supplier',
            'godown',
            'payments',
        ]);

        $paper = match ($size) {
            'half' => ['paper' => 'a5', 'orientation' => 'portrait'],
            'quarter' => ['paper' => [105, 148], 'orientation' => 'portrait'],
            default => ['paper' => 'a4', 'orientation' => 'portrait'],
        };

        if ($lang === 'mr' && ! PrintFonts::ensureInstalled()) {
            $lang = 'en';
        }

        $mpdf = PdfMaker::fromView('purchases.pdf', [
            'purchase' => $purchase,
            'company' => $company,
            'printSize' => $size,
            'printLang' => $lang,
            'printFontFamily' => PrintFonts::cssFamily($lang),
        ], $paper, $lang);

        $file = 'purchase-'.str_replace('/', '-', $purchase->purchase_no).'-'.$size.'.pdf';

        return PdfMaker::response($mpdf, $file, $request->boolean('download'));
    }

    /** Browser print preview with PDF embed. */
    public function printPreview(Request $request, Purchase $purchase)
    {
        $this->authorizeGodown($request, $purchase->godown_id);
        $company = CompanyProfile::current();
        $size = (string) $request->input('size', PrintFormats::defaultSaleSize($company));
        if (! in_array($size, ['a4', 'half', 'quarter'], true)) {
            $size = 'a4';
        }
        $lang = (string) $request->input('lang', PrintFormats::defaultSaleLang($company));
        if (! in_array($lang, ['en', 'mr'], true)) {
            $lang = 'en';
        }

        $purchase->load(['supplier', 'godown']);

        return view('purchases.print-browser', [
            'purchase' => $purchase,
            'company' => $company,
            'printSize' => $size,
            'printLang' => $lang,
        ]);
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

        return redirect()->route('purchases.show', $purchase)->with('status', __('Purchase :no cancelled.', ['no' => $purchase->purchase_no]));
    }
}
