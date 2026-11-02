<?php

namespace App\Http\Controllers;

use App\Models\Ledger;
use App\Models\Purchase;
use App\Services\SettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** One supplier payment held against many open purchase bills (FIFO / partial). */
class BulkPaymentController extends Controller
{
    public function __construct(private SettlementService $settle) {}

    public function create(Request $request)
    {
        $godownIds = $request->user()->accessibleGodownIds();
        $partyId = (int) $request->input('party_id', 0);

        $parties = Ledger::suppliers()->where('is_active', true)
            ->whereHas('purchases', fn ($q) => $q->where('status', 'active')
                ->whereIn('godown_id', $godownIds)
                ->openDue())
            ->orderBy('name')
            ->get(['id', 'name', 'name_mr', 'phone']);

        // Keep the selected supplier in the list after settle (no open dues left).
        if ($partyId > 0 && ! $parties->contains(fn ($p) => (int) $p->id === $partyId)) {
            $picked = Ledger::suppliers()->whereKey($partyId)->first(['id', 'name', 'name_mr', 'phone']);
            if ($picked) {
                $parties = $parties->prepend($picked)->values();
            }
        }

        $dues = collect();
        if ($partyId > 0) {
            $dues = Purchase::with('supplier')
                ->where('status', 'active')
                ->where('supplier_ledger_id', $partyId)
                ->whereIn('godown_id', $godownIds)
                ->openDue()
                ->orderBy('purchase_date')
                ->orderBy('id')
                ->get();
        }

        return view('vouchers.bulk-settle', [
            'kind' => 'payment',
            'title' => __('Bulk Payment'),
            'blurb' => __('Pay a supplier once. Select bills — oldest clear first; the last may be partial.'),
            'storeRoute' => route('bulk-payments.store'),
            'filterRoute' => route('bulk-payments.create'),
            'parties' => $parties,
            'partyId' => $partyId,
            'partyLabel' => __('Supplier'),
            'dues' => $dues,
            'billLabel' => __('Bill'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'party_id' => ['required', 'integer', 'exists:ledgers,id'],
            'payment_date' => ['required', 'date'],
            'ledger_id' => Ledger::paymentInstrumentRule(true, 'mode'),
            'mode' => ['required_without:ledger_id', 'nullable', 'in:cash,bank,upi'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:100'],
            'bill_ids' => ['required', 'array', 'min:1'],
            'bill_ids.*' => ['integer', 'exists:purchases,id'],
            'allocate' => ['nullable', 'array'],
            'allocate.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        $this->assertActiveFyDate($data['payment_date'], 'payment_date');

        try {
            $result = $this->settle->bulkPayment($data, $request->user()->accessibleGodownIds(), $request->user()->id);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()
            ->route('bulk-payments.create', ['party_id' => $data['party_id']])
            ->with('status', __('Bulk payment :ref — :count bill(s), ₹ :amt settled.', [
                'ref' => $result['reference'],
                'count' => $result['count'],
                'amt' => money($result['applied']),
            ]));
    }
}
