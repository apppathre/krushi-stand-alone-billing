<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\FinancialYearService;
use App\Services\InvoiceNumberGenerator;
use App\Services\SaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SaleReturnController extends Controller
{
    public function __construct(
        private SaleService $sales,
        private InvoiceNumberGenerator $numbers,
    ) {}

    public function index(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $rows = SaleReturn::with(['client', 'sale', 'godown'])
            ->whereIn('godown_id', $this->godownScope($request))
            ->whereDate('return_date', '>=', $from)->whereDate('return_date', '<=', $to)
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = '%'.$request->q.'%';
                $q->where(fn ($w) => $w->where('return_no', 'like', $t)
                    ->orWhereHas('sale', fn ($s) => $s->where('invoice_no', 'like', $t))
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $t)));
            })
            ->orderByDesc('return_date')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('returns.index', ['rows' => $rows, 'from' => $from, 'to' => $to, 'godowns' => $request->user()->accessibleGodowns()]);
    }

    /** Step 1: pick an invoice; step 2 (?sale_id=): choose quantities to return. */
    public function create(Request $request)
    {
        if ($request->filled('sale_id')) {
            $sale = Sale::with(['items.product.unit', 'items.batch', 'client'])->findOrFail($request->sale_id);
            $this->authorizeGodown($request, $sale->godown_id);
            abort_if($sale->isCancelled(), 404);
            $voucherDate = old('return_date', app(FinancialYearService::class)->defaultVoucherDate());

            return view('returns.create', [
                'sale' => $sale,
                'numberFormats' => $this->numbers->formatsFor('credit_note', $voucherDate),
            ]);
        }

        $sales = Sale::with('client')->where('status', 'active')
            ->whereIn('godown_id', $request->user()->accessibleGodownIds())
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = '%'.$request->q.'%';
                $q->where(fn ($w) => $w->where('invoice_no', 'like', $t)->orWhere('customer_name', 'like', $t)
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $t)->orWhere('phone', 'like', $t)));
            })
            ->orderByDesc('invoice_date')->orderByDesc('id')->paginate(10)->withQueryString();

        return view('returns.pick', compact('sales'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sale_id' => ['required', 'exists:sales,id'],
            'return_date' => ['required', 'date'],
            'document_series_id' => ['nullable', 'integer', 'exists:document_series,id'],
            'reason' => ['nullable', 'string', 'max:255'],
            'qty' => ['required', 'array'],
            'qty.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        $this->assertActiveFyDate($data['return_date'], 'return_date');
        $sale = Sale::findOrFail($data['sale_id']);
        $this->authorizeGodown($request, $sale->godown_id);

        try {
            $return = $this->sales->createReturn(
                $sale,
                $data['qty'],
                $data['return_date'],
                $data['reason'] ?? null,
                $request->user()->id,
                isset($data['document_series_id']) ? (int) $data['document_series_id'] : null
            );
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['items' => $e->getMessage()]);
        }

        return redirect()->route('sale-returns.show', $return)->with('status', __('Credit note :no saved successfully.', ['no' => $return->return_no]));
    }

    public function show(Request $request, SaleReturn $saleReturn)
    {
        $this->authorizeGodown($request, $saleReturn->godown_id);
        $saleReturn->load(['items.product.unit', 'sale', 'client', 'godown']);

        return view('returns.show', ['return' => $saleReturn, 'company' => CompanyProfile::current()]);
    }
}
