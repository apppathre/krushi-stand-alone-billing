<?php

namespace App\Http\Controllers;

use App\Models\Ledger;
use App\Models\Sale;
use App\Services\SettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** One customer receipt held against many open invoices (FIFO / partial). */
class BulkReceiptController extends Controller
{
    public function __construct(private SettlementService $settle) {}

    public function create(Request $request)
    {
        $godownIds = $request->user()->accessibleGodownIds();
        $partyId = (int) $request->input('party_id', 0);

        $parties = Ledger::clients()->where('is_active', true)
            ->whereHas('sales', fn ($q) => $q->where('status', 'active')
                ->whereIn('godown_id', $godownIds)
                ->openDue())
            ->orderBy('name')
            ->get(['id', 'name', 'name_mr', 'phone']);

        $dues = collect();
        if ($partyId > 0) {
            $dues = Sale::with('client')
                ->where('status', 'active')
                ->where('client_ledger_id', $partyId)
                ->whereIn('godown_id', $godownIds)
                ->openDue()
                ->orderBy('invoice_date')
                ->orderBy('id')
                ->get();
        }

        return view('vouchers.bulk-settle', [
            'kind' => 'receipt',
            'title' => __('Bulk Receipt'),
            'blurb' => __('Customer pays once. Select invoices — oldest clear first; the last may be partial.'),
            'storeRoute' => route('bulk-receipts.store'),
            'filterRoute' => route('bulk-receipts.create'),
            'parties' => $parties,
            'partyId' => $partyId,
            'partyLabel' => __('Customer'),
            'dues' => $dues,
            'billLabel' => __('Invoice'),
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
            'bill_ids.*' => ['integer', 'exists:sales,id'],
            'allocate' => ['nullable', 'array'],
            'allocate.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        $this->assertActiveFyDate($data['payment_date'], 'payment_date');

        try {
            $result = $this->settle->bulkReceipt($data, $request->user()->accessibleGodownIds(), $request->user()->id);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()
            ->route('bulk-receipts.create', ['party_id' => $data['party_id']])
            ->with('status', __('Bulk receipt :ref — :count bill(s), ₹ :amt settled.', [
                'ref' => $result['reference'],
                'count' => $result['count'],
                'amt' => money($result['applied']),
            ]));
    }
}
