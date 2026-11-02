<?php

namespace App\Http\Controllers;

use App\Models\AccountMap;
use App\Models\CompanyProfile;
use App\Models\GstSlab;
use App\Models\Ledger;
use App\Models\Sale;
use App\Services\FinancialYearService;
use App\Services\InvoiceNumberGenerator;
use App\Services\ProductCatalog;
use App\Services\SaleService;
use App\Support\IndianStates;
use App\Support\LedgerBalance;
use App\Support\PrintFormats;
use App\Support\TermsHtml;
use App\Support\WorkContext;
use App\Support\PrintFonts;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SaleController extends Controller
{
    public function __construct(
        private SaleService $sales,
        private InvoiceNumberGenerator $numbers,
    ) {}

    /** Sales desk: entry form only — recent list opens in a wide drawer. */
    public function index(Request $request, ProductCatalog $catalog)
    {
        $godownId = WorkContext::placeId($request);
        $walkInId = (int) (AccountMap::current()->walk_in_ledger_id ?: 0);
        $voucherDate = old('invoice_date', app(FinancialYearService::class)->defaultVoucherDate());

        $placeIds = $request->user()->accessibleGodownIds();

        return view('sales.index', [
            'clients' => $this->saleClients($walkInId),
            'walkInId' => $walkInId,
            'products' => $catalog->forSale($placeIds),
            'states' => IndianStates::options(),
            'companyState' => CompanyProfile::current()->state_code,
            'defaultCreditDays' => (int) (CompanyProfile::current()->default_credit_days ?? 30),
            'places' => $request->user()->accessibleGodowns(),
            'placeId' => $godownId,
            'gstRates' => GstSlab::where('is_active', true)->orderBy('sort_order')->orderBy('rate')->pluck('rate')
                ->map(fn ($r) => (float) $r)->unique()->values()->all() ?: [0, 5, 12, 18, 28],
            'numberFormats' => $this->numbers->formatsFor('sale', $voucherDate),
            'defaultTerms' => TermsHtml::fromCompany(CompanyProfile::current()->invoice_terms),
            'voucherDate' => $voucherDate,
        ]);
    }

    /** Wide drawer / AJAX browser for recent invoices. */
    public function browse(Request $request)
    {
        [$from, $to] = $this->listDateRange($request);
        $rows = $this->saleQuery($request, $from, $to)->paginate(15)->withQueryString();

        if ($request->ajax() || $request->boolean('list')) {
            return view('sales._list', compact('rows'));
        }

        return view('sales._browse', [
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'filterClients' => Ledger::clients()->orderBy('name')->get(['id', 'name', 'name_mr', 'phone']),
            'godowns' => $request->user()->accessibleGodowns(),
        ]);
    }

    private function saleQuery(Request $request, string $from, string $to)
    {
        return Sale::with(['client', 'godown'])
            ->whereIn('godown_id', $this->godownScope($request))
            ->whereDate('invoice_date', '>=', $from)->whereDate('invoice_date', '<=', $to)
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = '%'.$request->q.'%';
                $q->where(fn ($w) => $w->where('invoice_no', 'like', $t)->orWhere('customer_name', 'like', $t)
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $t)->orWhere('phone', 'like', $t)));
            })
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_ledger_id', $request->client_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->input('payment') === 'due', fn ($q) => $q->where('status', 'active')->openDue())
            ->orderByDesc('invoice_date')->orderByDesc('id');
    }

    public function create(Request $request)
    {
        return redirect()->route('sales.index', $request->only('godown_id'));
    }

    public function store(Request $request): RedirectResponse
    {
        $walkInId = (int) (AccountMap::current()->walk_in_ledger_id ?: 0);
        $data = $request->validate([
            'invoice_date' => ['required', 'date'],
            'document_series_id' => ['nullable', 'integer', 'exists:document_series,id'],
            'godown_id' => ['required', 'integer'],
            'client_ledger_id' => [
                'required',
                'integer',
                Rule::exists('ledgers', 'id')->where(fn ($q) => $q->whereIn('type', ['client', 'both'])->where('is_active', true)),
                Rule::notIn($walkInId > 0 ? [$walkInId] : []),
            ],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'supply_state_code' => ['nullable', 'in:'.implode(',', array_keys(IndianStates::LIST))],
            'notes' => ['nullable', 'string', 'max:500'],
            'terms' => ['nullable', 'string', 'max:20000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.godown_id' => ['nullable', 'integer'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.batch_id' => ['nullable', 'exists:product_batches,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
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
            'after_save' => ['nullable', 'in:show,print'],
        ], [
            'client_ledger_id.required' => __('Select a customer ledger. Walk-in is not allowed.'),
            'client_ledger_id.not_in' => __('Select a customer ledger. Walk-in is not allowed.'),
        ]);

        $settlement = $data['settlement_type']
            ?? (((float) ($data['paid_amount'] ?? 0) > 0) ? 'cash' : 'credit');
        $data['settlement_type'] = $settlement;
        if ($settlement === 'credit') {
            $data['paid_amount'] = 0;
        }

        $this->assertActiveFyDate($data['invoice_date'], 'invoice_date');
        $godownId = (int) $data['godown_id'];
        $this->authorizeGodown($request, $godownId);
        foreach ($data['items'] as $row) {
            $linePlace = (int) ($row['godown_id'] ?? $godownId);
            $this->authorizeGodown($request, $linePlace);
        }
        $request->session()->put('godown_id', $godownId);

        $afterSave = $data['after_save'] ?? 'show';
        unset($data['after_save']);

        try {
            $sale = $this->sales->create($data + ['godown_id' => $godownId, 'customer_name' => null], $request->user()->id);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['items' => $e->getMessage()]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        $status = __('Invoice :no saved successfully.', ['no' => $sale->invoice_no]);
        if ($afterSave === 'print') {
            $company = CompanyProfile::current();
            $format = PrintFormats::defaultSaleFormat($company);
            $size = PrintFormats::defaultSaleSize($company);
            $lang = PrintFormats::defaultSaleLang($company);
            $meta = PrintFormats::resolveSale($format);
            $allowed = $meta['sizes'] ?? ['a4'];
            if (! in_array($size, $allowed, true)) {
                $size = $meta['default_size'] ?? $allowed[0];
            }

            return redirect()
                ->route('sales.pdf', [$sale, 'format' => $format, 'size' => $size, 'lang' => $lang])
                ->with('status', $status);
        }

        return redirect()->route('sales.show', $sale)->with('status', $status);
    }

    public function show(Request $request, Sale $sale)
    {
        $this->authorizeGodown($request, $sale->godown_id);
        $sale->load(['items.product.unit', 'client', 'godown', 'payments.ledger', 'user']);

        return view('sales.show', compact('sale'));
    }

    /** Full line / tax / terms view — summary stays on show. */
    public function details(Request $request, Sale $sale)
    {
        $this->authorizeGodown($request, $sale->godown_id);
        $sale->load(['items.product.unit', 'items.godown', 'items.batch.rack', 'client', 'godown', 'payments.ledger', 'returns', 'user']);

        return view('sales.details', compact('sale'));
    }

    public function pdf(Request $request, Sale $sale)
    {
        $this->authorizeGodown($request, $sale->godown_id);
        $company = CompanyProfile::current();
        $format = (string) $request->input('format', PrintFormats::defaultSaleFormat($company));
        $meta = PrintFormats::resolveSale($format);
        $allowedSizes = $meta['sizes'] ?? ['a4'];
        $size = (string) $request->input('size', $meta['default_size'] ?? 'a4');
        if (! in_array($size, $allowedSizes, true)) {
            $size = $meta['default_size'] ?? $allowedSizes[0];
        }
        $lang = (string) $request->input('lang', PrintFormats::defaultSaleLang($company));
        if (! in_array($lang, ['en', 'mr'], true)) {
            $lang = 'en';
        }

        $sale->load([
            'items.product.unit',
            'items.product.manufacturer',
            'items.batch',
            'client.area',
            'godown',
            'payments',
        ]);

        $orientation = ($meta['orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
        $paper = match ($size) {
            'half' => ['paper' => 'a5', 'orientation' => 'portrait'],
            'quarter' => ['paper' => [0, 0, 298, 420], 'orientation' => 'portrait'],
            default => ['paper' => 'a4', 'orientation' => $orientation],
        };

        $partyBal = $sale->client_ledger_id
            ? LedgerBalance::asOf((int) $sale->client_ledger_id)
            : ['amount' => 0.0, 'side' => 'dr', 'label' => '0.00 Dr', 'signed' => 0.0];

        // Marathi PDF needs Noto Sans Devanagari — fall back to English if font missing.
        if ($lang === 'mr' && ! PrintFonts::ensureInstalled()) {
            $lang = 'en';
        }

        $pdf = Pdf::loadView($meta['view'], [
            'sale' => $sale,
            'company' => $company,
            'printSize' => $size,
            'printFormat' => $format,
            'printLang' => $lang,
            'printFontFamily' => PrintFonts::cssFamily($lang),
            'partyBalance' => $partyBal,
        ])->setPaper($paper['paper'], $paper['orientation']);
        PrintFonts::applyToPdf($pdf);

        $file = 'invoice-'.str_replace('/', '-', $sale->invoice_no).'-'.$format.'-'.$size.'.pdf';

        return $request->boolean('download') ? $pdf->download($file) : $pdf->stream($file);
    }

    /**
     * Browser print preview (HTML + web Devanagari fonts).
     * Best option when DomPDF Marathi font is missing, or for staff who prefer Ctrl+P.
     */
    public function printPreview(Request $request, Sale $sale)
    {
        $this->authorizeGodown($request, $sale->godown_id);
        $company = CompanyProfile::current();
        $format = (string) $request->input('format', PrintFormats::defaultSaleFormat($company));
        $meta = PrintFormats::resolveSale($format);
        $size = (string) $request->input('size', $meta['default_size'] ?? 'a4');
        $lang = (string) $request->input('lang', PrintFormats::defaultSaleLang($company));
        if (! in_array($lang, ['en', 'mr'], true)) {
            $lang = 'en';
        }

        $sale->load([
            'items.product.unit',
            'items.product.manufacturer',
            'items.batch',
            'client.area',
            'godown',
            'payments',
        ]);

        $partyBal = $sale->client_ledger_id
            ? LedgerBalance::asOf((int) $sale->client_ledger_id)
            : ['amount' => 0.0, 'side' => 'dr', 'label' => '0.00 Dr', 'signed' => 0.0];

        return view('sales.print-browser', [
            'sale' => $sale,
            'company' => $company,
            'printSize' => $size,
            'printFormat' => $format,
            'printLang' => $lang,
            'partyBalance' => $partyBal,
            'pdfFontOk' => PrintFonts::marathiAvailable(),
            'pdfMeta' => $meta,
        ]);
    }

    /** Delivery challan / note — place + rack pick locations (no tax columns). */
    public function dc(Request $request, Sale $sale)
    {
        $this->authorizeGodown($request, $sale->godown_id);
        $company = CompanyProfile::current();
        $size = (string) $request->input('size', 'a4');
        if (! in_array($size, ['a4', 'half', 'quarter'], true)) {
            $size = 'a4';
        }
        $lang = (string) $request->input('lang', PrintFormats::defaultSaleLang($company));
        if (! in_array($lang, ['en', 'mr'], true)) {
            $lang = 'en';
        }

        $sale->load([
            'items.product.unit',
            'items.godown',
            'items.batch.rack',
            'client',
            'godown',
        ]);

        $paper = match ($size) {
            'half' => ['paper' => 'a5', 'orientation' => 'portrait'],
            'quarter' => ['paper' => [0, 0, 298, 420], 'orientation' => 'portrait'],
            default => ['paper' => 'a4', 'orientation' => 'portrait'],
        };

        if ($lang === 'mr' && ! PrintFonts::ensureInstalled()) {
            $lang = 'en';
        }

        $pdf = Pdf::loadView('sales.pdf-dc', [
            'sale' => $sale,
            'company' => $company,
            'printSize' => $size,
            'printLang' => $lang,
            'printFontFamily' => PrintFonts::cssFamily($lang),
        ])->setPaper($paper['paper'], $paper['orientation']);
        PrintFonts::applyToPdf($pdf);

        $file = 'dc-'.str_replace('/', '-', $sale->invoice_no).'-'.$size.'.pdf';

        return $request->boolean('download') ? $pdf->download($file) : $pdf->stream($file);
    }

    public function payment(Request $request, Sale $sale): RedirectResponse
    {
        $this->authorizeGodown($request, $sale->godown_id);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'ledger_id' => Ledger::paymentInstrumentRule(true, 'mode'),
            'mode' => ['required_without:ledger_id', 'nullable', 'in:cash,bank,upi'],
            'payment_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);
        $this->assertActiveFyDate($data['payment_date'], 'payment_date');

        try {
            $this->sales->addPayment($sale, $data, $request->user()->id);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', __('Payment recorded.'));
    }

    public function cancel(Request $request, Sale $sale): RedirectResponse
    {
        $this->authorizeGodown($request, $sale->godown_id);
        $this->sales->cancel($sale);

        return redirect()->route('sales.show', $sale)->with('status', __('Invoice :no cancelled.', ['no' => $sale->invoice_no]));
    }

    private function saleClients(int $walkInId)
    {
        return Ledger::clients()->where('is_active', true)
            ->when($walkInId > 0, fn ($q) => $q->where('id', '!=', $walkInId))
            ->orderBy('name')
            ->get(['id', 'name', 'name_mr', 'phone', 'state_code', 'credit_days']);
    }
}
