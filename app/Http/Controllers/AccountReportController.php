<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Ledger;
use App\Models\Manufacturer;
use App\Services\AccountVoucherService;
use App\Services\GstExceptionService;
use App\Services\GstReturnService;
use App\Services\ReportService;
use App\Support\ReportTableExport;
use Illuminate\Http\Request;

class AccountReportController extends Controller
{
    public function __construct(
        private ReportService $reports,
        private GstReturnService $gstReturns,
        private GstExceptionService $gstExceptions,
    ) {}

    public function partyLedger(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $ledgers = Ledger::where('is_active', true)->orderBy('family')->orderBy('name')->get();
        $ledger = $request->filled('ledger_id') ? Ledger::find($request->ledger_id) : null;
        $statement = $ledger ? $this->reports->ledgerStatement($ledger, $from, $to) : null;

        return view('reports.party-ledger', compact('ledgers', 'ledger', 'statement', 'from', 'to'));
    }

    public function stock(Request $request)
    {
        $scope = $this->godownScope($request); // selected place or all accessible (never whole DB)
        $godownId = (int) $request->input('godown_id');
        if ($godownId && ! in_array($godownId, $request->user()->accessibleGodownIds(), true)) {
            $godownId = 0;
        }
        $categoryId = $request->filled('category_id') ? (int) $request->category_id : null;
        $data = $this->reports->stockReport(
            $godownId ? $godownId : $scope,
            $categoryId
        );

        return view('reports.stock', $data + [
            'godownId' => $godownId ?: null,
            'categoryId' => $categoryId,
            'categories' => Category::orderBy('name')->get(),
            'godowns' => $request->user()->accessibleGodowns(),
        ]);
    }

    public function priceList(Request $request)
    {
        $categoryId = $request->filled('category_id') ? (int) $request->category_id : null;
        $manufacturerId = $request->filled('manufacturer_id') ? (int) $request->manufacturer_id : null;
        $data = $this->reports->priceList($categoryId, $manufacturerId);

        return view('reports.price-list', $data + [
            'categoryId' => $categoryId,
            'manufacturerId' => $manufacturerId,
            'categories' => Category::orderBy('name')->get(),
            'manufacturers' => Manufacturer::orderBy('name')->get(),
        ]);
    }

    public function outstanding(Request $request)
    {
        $view = $request->input('view', 'party'); // party | ageing
        $side = $request->input('side'); // receivable|payable|null
        $company = \App\Models\CompanyProfile::current();

        if ($view === 'ageing') {
            $ageSide = in_array($side, ['receivable', 'payable'], true) ? $side : 'receivable';
            $interestPct = $request->filled('interest_pct')
                ? (float) $request->input('interest_pct')
                : (float) ($company->overdue_interest_pct ?? 0);
            $asOf = $request->input('as_of') ?: now()->toDateString();
            $data = $this->reports->outstandingAgeing($ageSide, $asOf, max(0, $interestPct));

            if ($export = $this->exportFormat($request)) {
                $headers = ['Bill', 'Party', 'Bill date', 'Due date', 'Balance', 'Overdue days', 'Bucket'];
                $rows = collect($data['rows'] ?? [])->map(fn ($r) => [
                    $r->doc_no ?? '',
                    $r->party_name ?? '',
                    optional($r->bill_date)->toDateString() ?? (string) $r->bill_date,
                    optional($r->due_date)->toDateString() ?? (string) $r->due_date,
                    money($r->balance ?? 0),
                    (string) ($r->overdue_days ?? 0),
                    (string) ($r->bucket ?? ''),
                ])->all();

                return ReportTableExport::download($export, 'outstanding-ageing-'.$asOf, $headers, $rows);
            }

            $incomeLedgers = \App\Models\Ledger::books()->where('is_active', true)
                ->whereIn('type', ['income', 'other'])
                ->orderBy('name')->get()
                ->mapWithKeys(fn ($l) => [$l->id => $l->display_name])->all();

            return view('reports.outstanding-ageing', $data + [
                'view' => 'ageing',
                'incomeLedgers' => $incomeLedgers,
                'defaultInterestPct' => (float) ($company->overdue_interest_pct ?? 0),
            ]);
        }

        $data = $this->reports->outstandingParties(in_array($side, ['receivable', 'payable'], true) ? $side : null);

        if ($export = $this->exportFormat($request)) {
            $headers = ['Side', 'Party', 'Phone', 'Balance'];
            $rows = [];
            foreach ($data['receivable_rows'] as $r) {
                $rows[] = ['receivable', (string) ($r->name ?? ''), (string) ($r->phone ?? ''), money((float) $r->bal)];
            }
            foreach ($data['payable_rows'] as $r) {
                $rows[] = ['payable', (string) ($r->name ?? ''), (string) ($r->phone ?? ''), money(abs((float) $r->bal))];
            }

            return ReportTableExport::download($export, 'outstanding-parties', $headers, $rows);
        }

        return view('reports.outstanding', $data + ['side' => $side, 'view' => 'party']);
    }

    /** Post simple overdue interest as a journal voucher (Dr party, Cr income). */
    public function applyInterest(Request $request, AccountVoucherService $vouchers)
    {
        abort_unless($request->user()->canMenu('report_outstanding', 'view'), 403);

        $data = $request->validate([
            'party_ledger_id' => ['required', 'integer', 'exists:ledgers,id'],
            'income_ledger_id' => ['required', 'integer', 'exists:ledgers,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'voucher_date' => ['required', 'date'],
            'narration' => ['nullable', 'string', 'max:500'],
        ]);
        $this->assertActiveFyDate($data['voucher_date'], 'voucher_date');

        try {
            $voucher = $vouchers->createInterestJournal($data, $request->user()->id);
        } catch (\Throwable $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', __('Interest posted as :no.', ['no' => $voucher->voucher_no]));
    }

    public function trialBalance(Request $request)
    {
        $asOf = $request->input('as_of') ?: now()->toDateString();
        $data = $this->reports->trialBalance($asOf);

        return view('reports.trial-balance', $data);
    }

    public function balanceSheet(Request $request)
    {
        $asOf = $request->input('as_of') ?: now()->toDateString();
        $data = $this->reports->balanceSheet($asOf, $this->godownScope($request));

        return view('reports.balance-sheet', $data);
    }

    /** Cash / bank book — same statement as ledger report, limited to cash & bank. */
    public function cashBook(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $instruments = Ledger::query()->paymentInstruments()->get();
        $ledger = $request->filled('ledger_id')
            ? $instruments->firstWhere('id', (int) $request->ledger_id)
            : $instruments->first();
        $statement = $ledger ? $this->reports->ledgerStatement($ledger, $from, $to) : null;

        return view('reports.cash-book', [
            'instruments' => $instruments,
            'ledger' => $ledger,
            'statement' => $statement,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function salesRegister(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $data = $this->reports->salesRegister($from, $to, $this->godownScope($request));

        if ($export = $this->exportFormat($request)) {
            $headers = ['Date', 'Invoice', 'Client', 'GSTIN', 'Taxable', 'CGST', 'SGST', 'IGST', 'Round off', 'Total'];
            $rows = $data['sales']->map(fn ($s) => [
                optional($s->invoice_date)->toDateString(),
                $s->invoice_no,
                $s->customer_name ?: ($s->client?->display_name ?? ''),
                (string) ($s->client?->gstin ?? ''),
                money($s->taxable_total),
                money($s->cgst_total),
                money($s->sgst_total),
                money($s->igst_total),
                money($s->round_off),
                money($s->grand_total),
            ])->all();

            return ReportTableExport::download($export, 'sales-register-'.$from.'-'.$to, $headers, $rows);
        }

        return view('reports.sales-register', $data + ['from' => $from, 'to' => $to, 'godowns' => $request->user()->accessibleGodowns()]);
    }

    public function purchaseRegister(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $data = $this->reports->purchaseRegister($from, $to, $this->godownScope($request));

        if ($export = $this->exportFormat($request)) {
            $headers = ['Date', 'Purchase no', 'Supplier', 'GSTIN', 'Taxable', 'CGST', 'SGST', 'IGST', 'Round off', 'Total'];
            $rows = $data['purchases']->map(fn ($p) => [
                optional($p->purchase_date)->toDateString(),
                $p->purchase_no,
                $p->supplier?->display_name ?? '',
                (string) ($p->supplier?->gstin ?? ''),
                money($p->taxable_total),
                money($p->cgst_total),
                money($p->sgst_total),
                money($p->igst_total),
                money($p->round_off),
                money($p->grand_total),
            ])->all();

            return ReportTableExport::download($export, 'purchase-register-'.$from.'-'.$to, $headers, $rows);
        }

        return view('reports.purchase-register', $data + ['from' => $from, 'to' => $to, 'godowns' => $request->user()->accessibleGodowns()]);
    }

    public function profitLoss(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $pl = $this->reports->profitAndLoss($from, $to, $this->godownScope($request));

        return view('reports.profit-loss', ['pl' => $pl, 'from' => $from, 'to' => $to, 'godowns' => $request->user()->accessibleGodowns()]);
    }

    /** GSTR-1 / GSTR-2 style pack + HSN for the period. */
    public function gstReturn(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $pack = $this->gstReturns->build($from, $to, $this->godownScope($request));

        if ($export = $this->exportFormat($request)) {
            $headers = ['Section', 'No', 'Date', 'Party', 'GSTIN', 'Taxable', 'CGST', 'SGST', 'IGST', 'Total'];
            $rows = [];
            foreach (['b2b', 'b2cl', 'b2c', 'cdnr', 'cdnur', 'purchases', 'purchase_returns'] as $section) {
                foreach ($pack[$section] ?? [] as $r) {
                    $rows[] = [
                        $section,
                        $r['number'] ?? '',
                        $r['date'] ?? '',
                        $r['party'] ?? '',
                        $r['gstin'] ?? '',
                        money($r['taxable'] ?? 0),
                        money($r['cgst'] ?? 0),
                        money($r['sgst'] ?? 0),
                        money($r['igst'] ?? 0),
                        money($r['grand'] ?? 0),
                    ];
                }
            }

            return ReportTableExport::download($export, 'gst-return-'.$from.'-'.$to, $headers, $rows);
        }

        return view('reports.gst-return', compact('pack', 'from', 'to'));
    }

    /**
     * GST exceptions audit — missing HSN / GSTIN, tax-split errors, rate overrides.
     * Data comes from sales + sale_items and purchases + purchase_items (with product GST).
     */
    public function gstException(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $type = (string) $request->input('type', 'all');
        $doc = (string) $request->input('doc', 'all');
        if (! in_array($doc, ['all', 'sale', 'purchase'], true)) {
            $doc = 'all';
        }
        $data = $this->gstExceptions->build($from, $to, $this->godownScope($request), $type, $doc);

        if ($export = $this->exportFormat($request)) {
            $headers = ['Doc', 'Date', 'Number', 'Party', 'GSTIN', 'Exception', 'Detail', 'Item'];
            $rows = $data['rows']->map(fn ($r) => [
                $r['doc'],
                $r['date'],
                $r['number'],
                $r['party'],
                $r['gstin'],
                $r['type_label'],
                $r['detail'],
                $r['item'] ?? '',
            ])->all();

            return ReportTableExport::download($export, 'gst-exceptions-'.$from.'-'.$to, $headers, $rows);
        }

        return view('reports.gst-exception', $data + [
            'godowns' => $request->user()->accessibleGodowns(),
            'typeOptions' => GstExceptionService::typeOptions(),
        ]);
    }

    public function dayBook(Request $request)
    {
        $date = $request->input('date') ?: now()->toDateString();
        $book = $this->reports->dayBook($date, $this->godownScope($request));

        if ($export = $this->exportFormat($request)) {
            $headers = ['Voucher', 'Type', 'Ledger', 'Narration', 'Debit', 'Credit'];
            $rows = [];
            foreach ($book['vouchers'] as $entries) {
                foreach ($entries as $e) {
                    $rows[] = [
                        (string) ($e->voucher_no ?? ''),
                        (string) ($e->voucher_type ?? ''),
                        (string) ($e->ledger?->display_name ?? ''),
                        (string) ($e->narration ?? ''),
                        money($e->debit ?? 0),
                        money($e->credit ?? 0),
                    ];
                }
            }

            return ReportTableExport::download($export, 'day-book-'.$date, $headers, $rows);
        }

        return view('reports.day-book', ['book' => $book, 'date' => $date, 'godowns' => $request->user()->accessibleGodowns()]);
    }

    private function exportFormat(Request $request): ?string
    {
        $format = strtolower((string) $request->input('export', ''));

        return in_array($format, ['csv', 'xls'], true) ? $format : null;
    }
}
