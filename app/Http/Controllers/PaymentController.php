<?php

namespace App\Http\Controllers;

use App\Models\Ledger;
use App\Models\Purchase;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Supplier payments against open purchase bills. */
class PaymentController extends Controller
{
    public function __construct(private PurchaseService $purchases) {}

    public function index(Request $request)
    {
        $godownIds = $request->user()->accessibleGodownIds();

        $dues = Purchase::with('supplier')
            ->where('status', 'active')
            ->whereIn('godown_id', $godownIds)
            ->openDue()
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_ledger_id', (int) $request->supplier_id))
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = '%'.$request->q.'%';
                $q->where(fn ($w) => $w->where('purchase_no', 'like', $t)
                    ->orWhere('supplier_invoice_no', 'like', $t)
                    ->orWhereHas('supplier', fn ($c) => $c->where('name', 'like', $t)->orWhere('phone', 'like', $t)));
            })
            ->orderByDesc('purchase_date')
            ->paginate(30)
            ->withQueryString();

        $suppliers = Ledger::suppliers()->where('is_active', true)->orderBy('name')->get()
            ->mapWithKeys(fn ($l) => [$l->id => $l->display_name])->all();

        return view('vouchers.payments', compact('dues', 'suppliers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'purchase_id' => ['required', 'exists:purchases,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'ledger_id' => Ledger::paymentInstrumentRule(true, 'mode'),
            'mode' => ['required_without:ledger_id', 'nullable', 'in:cash,bank,upi'],
            'payment_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $this->assertActiveFyDate($data['payment_date'], 'payment_date');
        $purchase = Purchase::findOrFail($data['purchase_id']);
        abort_unless(in_array($purchase->godown_id, $request->user()->accessibleGodownIds(), true), 403);

        try {
            $this->purchases->addPayment($purchase, $data);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', __('Payment recorded.'));
    }
}
