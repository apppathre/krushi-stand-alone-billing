<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Services\FinancialYearService;
use App\Services\InvoiceNumberGenerator;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PurchaseReturnController extends Controller
{
    public function __construct(
        private PurchaseService $purchases,
        private InvoiceNumberGenerator $numbers,
    ) {}

    public function index(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $rows = PurchaseReturn::with(['supplier', 'purchase', 'godown'])
            ->whereIn('godown_id', $this->godownScope($request))
            ->whereDate('return_date', '>=', $from)->whereDate('return_date', '<=', $to)
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = '%'.$request->q.'%';
                $q->where(fn ($w) => $w->where('return_no', 'like', $t)
                    ->orWhereHas('purchase', fn ($p) => $p->where('purchase_no', 'like', $t)->orWhere('supplier_invoice_no', 'like', $t))
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $t)));
            })
            ->orderByDesc('return_date')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('purchase-returns.index', [
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'godowns' => $request->user()->accessibleGodowns(),
        ]);
    }

    public function create(Request $request)
    {
        if ($request->filled('purchase_id')) {
            $purchase = Purchase::with(['items.product.unit', 'items.batch', 'supplier'])->findOrFail($request->purchase_id);
            $this->authorizeGodown($request, $purchase->godown_id);
            abort_if($purchase->isCancelled(), 404);
            $voucherDate = old('return_date', app(FinancialYearService::class)->defaultVoucherDate());

            return view('purchase-returns.create', [
                'purchase' => $purchase,
                'numberFormats' => $this->numbers->formatsFor('purchase_return', $voucherDate),
            ]);
        }

        $purchases = Purchase::with('supplier')->where('status', 'active')
            ->whereIn('godown_id', $request->user()->accessibleGodownIds())
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = '%'.$request->q.'%';
                $q->where(fn ($w) => $w->where('purchase_no', 'like', $t)->orWhere('supplier_invoice_no', 'like', $t)
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $t)));
            })
            ->orderByDesc('purchase_date')->orderByDesc('id')->paginate(10)->withQueryString();

        return view('purchase-returns.pick', compact('purchases'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'purchase_id' => ['required', 'exists:purchases,id'],
            'return_date' => ['required', 'date'],
            'document_series_id' => ['nullable', 'integer', 'exists:document_series,id'],
            'reason' => ['nullable', 'string', 'max:255'],
            'qty' => ['required', 'array'],
            'qty.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        $this->assertActiveFyDate($data['return_date'], 'return_date');
        $purchase = Purchase::findOrFail($data['purchase_id']);
        $this->authorizeGodown($request, $purchase->godown_id);

        try {
            $return = $this->purchases->createReturn(
                $purchase,
                $data['qty'],
                $data['return_date'],
                $data['reason'] ?? null,
                $request->user()->id,
                isset($data['document_series_id']) ? (int) $data['document_series_id'] : null
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('purchase-returns.show', $return)
            ->with('status', __('Purchase return :no created.', ['no' => $return->return_no]));
    }

    public function show(Request $request, PurchaseReturn $purchaseReturn)
    {
        $this->authorizeGodown($request, $purchaseReturn->godown_id);
        $purchaseReturn->load(['items.product.unit', 'purchase', 'supplier', 'godown']);

        return view('purchase-returns.show', ['return' => $purchaseReturn]);
    }
}
