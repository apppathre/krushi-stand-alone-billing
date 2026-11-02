<?php

namespace App\Http\Controllers;

use App\Models\Ledger;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Customer receipts against open sales invoices. */
class ReceiptController extends Controller
{
    public function __construct(private SaleService $sales) {}

    public function index(Request $request)
    {
        $godownIds = $request->user()->accessibleGodownIds();

        $dues = Sale::with('client')
            ->where('status', 'active')
            ->whereIn('godown_id', $godownIds)
            ->openDue()
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_ledger_id', (int) $request->client_id))
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = '%'.$request->q.'%';
                $q->where(fn ($w) => $w->where('invoice_no', 'like', $t)
                    ->orWhere('customer_name', 'like', $t)
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $t)->orWhere('phone', 'like', $t)));
            })
            ->orderByDesc('invoice_date')
            ->paginate(30)
            ->withQueryString();

        $clients = Ledger::clients()->where('is_active', true)->orderBy('name')->get()
            ->mapWithKeys(fn ($l) => [$l->id => $l->display_name])->all();

        return view('vouchers.receipts', compact('dues', 'clients'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sale_id' => ['required', 'exists:sales,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'ledger_id' => Ledger::paymentInstrumentRule(true, 'mode'),
            'mode' => ['required_without:ledger_id', 'nullable', 'in:cash,bank,upi'],
            'payment_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $this->assertActiveFyDate($data['payment_date'], 'payment_date');
        $sale = Sale::findOrFail($data['sale_id']);
        abort_unless(in_array($sale->godown_id, $request->user()->accessibleGodownIds(), true), 403);

        try {
            $this->sales->addPayment($sale, $data, $request->user()->id);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', __('Receipt recorded.'));
    }
}
