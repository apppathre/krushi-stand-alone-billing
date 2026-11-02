<?php

namespace App\Http\Controllers;

use App\Models\StockJournal;
use App\Services\FinancialYearService;
use App\Services\InvoiceNumberGenerator;
use App\Services\ProductCatalog;
use App\Services\StockJournalService;
use App\Support\WorkContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StockJournalController extends Controller
{
    private const META = [
        'transfer' => ['Stock Transfer', 'साठा हस्तांतरण', 'Move stock from one place to another — out + in on the same stock log.'],
        'journal' => ['Stock Journal', 'साठा जर्नल', 'Consume and produce lines — stock only, no money.'],
        'rejection' => ['Stock Rejection', 'साठा नाकारणे', 'Write off damaged or rejected stock (out).'],
        'issue' => ['Stock Issue', 'साठा निर्गम', 'Issue stock out for use or consumption.'],
        'physical' => ['Physical Stock', 'भौतिक साठा', 'Enter counted qty; only the difference posts.'],
        'return' => ['Stock Return', 'साठा परतावा', 'Bring unused issued stock back in.'],
    ];

    public function __construct(
        private StockJournalService $journals,
        private InvoiceNumberGenerator $numbers,
    ) {}

    public static function menuKey(string $type): string
    {
        return match ($type) {
            'rejection' => 'stock_rejection',
            'issue' => 'stock_issue',
            'physical' => 'physical_stock',
            'return' => 'stock_return',
            'transfer' => 'stock_transfer',
            default => 'stock_journal',
        };
    }

    public function index(Request $request, ProductCatalog $catalog, string $type = 'journal')
    {
        abort_unless(isset(self::META[$type]), 404);
        [$title, $titleMr, $blurb] = self::META[$type];
        $placeId = WorkContext::placeId($request);
        $voucherDate = old('journal_date', app(FinancialYearService::class)->defaultVoucherDate());
        $doc = StockJournalService::TYPES[$type]['doc'];
        $placeIds = $request->user()->accessibleGodownIds();

        return view('stock-journals.index', [
            'type' => $type,
            'title' => app()->getLocale() === 'mr' ? $titleMr : $title,
            'blurb' => __($blurb),
            'types' => self::META,
            'places' => $request->user()->accessibleGodowns(),
            'placeId' => $placeId,
            'toPlaceId' => old('to_godown_id'),
            'products' => $catalog->forSale($placeIds),
            'catalogIn' => $catalog->forPurchase(),
            'numberFormats' => $this->numbers->formatsFor($doc, $voucherDate),
            'docType' => $doc,
            'voucherDate' => $voucherDate,
            'menuKey' => self::menuKey($type),
            'editing' => null,
            'editItems' => [],
        ]);
    }

    public function store(Request $request, string $type = 'journal'): RedirectResponse
    {
        abort_unless(isset(self::META[$type]), 404);
        abort_unless($request->user()->canMenu(self::menuKey($type), 'add'), 403);

        $data = $this->validated($request, $type);
        $this->assertActiveFyDate($data['journal_date'], 'journal_date');
        $godownId = (int) $data['godown_id'];
        $this->authorizeGodown($request, $godownId);
        if ($type === 'transfer') {
            $this->authorizeGodown($request, (int) $data['to_godown_id']);
        }
        foreach ($data['items'] as $row) {
            $this->authorizeGodown($request, (int) ($row['godown_id'] ?? $godownId));
        }
        $request->session()->put('godown_id', $godownId);

        try {
            $journal = $this->journals->create($data + ['type' => $type, 'godown_id' => $godownId], $request->user()->id);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('stock-journals.show', $journal)
            ->with('status', __(':type :no saved.', ['type' => __(self::META[$type][0]), 'no' => $journal->journal_no]));
    }

    public function browse(Request $request, string $type = 'journal')
    {
        abort_unless(isset(self::META[$type]), 404);
        [$from, $to] = $this->listDateRange($request);
        $scope = $this->godownScope($request);

        $rows = StockJournal::with(['godown', 'toGodown'])
            ->where('type', $type)
            ->where(function ($q) use ($scope) {
                $q->whereIn('godown_id', $scope)
                    ->orWhereIn('to_godown_id', $scope);
            })
            ->whereDate('journal_date', '>=', $from)->whereDate('journal_date', '<=', $to)
            ->when($request->filled('q'), fn ($q) => $q->where('journal_no', 'like', '%'.$request->q.'%'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('journal_date')->orderByDesc('id')
            ->paginate(15)->withQueryString();

        if ($request->ajax() || $request->boolean('list')) {
            return view('stock-journals._list', [
                'rows' => $rows,
                'type' => $type,
                'menuKey' => self::menuKey($type),
            ]);
        }

        return view('stock-journals._browse', [
            'rows' => $rows,
            'type' => $type,
            'from' => $from,
            'to' => $to,
            'godowns' => $request->user()->accessibleGodowns(),
            'title' => __(self::META[$type][0]),
            'menuKey' => self::menuKey($type),
        ]);
    }

    public function show(Request $request, StockJournal $stockJournal)
    {
        $this->authorizeJournalAccess($request, $stockJournal);
        $stockJournal->load(['lines.product.unit', 'lines.batch', 'lines.godown', 'godown', 'toGodown', 'user']);
        $menuKey = self::menuKey($stockJournal->type);

        return view('stock-journals.show', [
            'journal' => $stockJournal,
            'title' => __(self::META[$stockJournal->type][0] ?? 'Stock Journal'),
            'menuKey' => $menuKey,
        ]);
    }

    public function edit(Request $request, StockJournal $stockJournal, ProductCatalog $catalog)
    {
        $this->authorizeJournalAccess($request, $stockJournal);
        abort_unless($stockJournal->status === 'active', 404);
        $type = $stockJournal->type;
        abort_unless(isset(self::META[$type]), 404);
        abort_unless($request->user()->canMenu(self::menuKey($type), 'edit'), 403);

        [$title, $titleMr, $blurb] = self::META[$type];
        $placeIds = $request->user()->accessibleGodownIds();
        $voucherDate = old('journal_date', $stockJournal->journal_date->toDateString());
        $doc = StockJournalService::TYPES[$type]['doc'];

        return view('stock-journals.index', [
            'type' => $type,
            'title' => app()->getLocale() === 'mr' ? $titleMr : $title,
            'blurb' => __($blurb),
            'types' => self::META,
            'places' => $request->user()->accessibleGodowns(),
            'placeId' => old('godown_id', $stockJournal->godown_id),
            'toPlaceId' => old('to_godown_id', $stockJournal->to_godown_id),
            'products' => $catalog->forSale($placeIds),
            'catalogIn' => $catalog->forPurchase(),
            'numberFormats' => $this->numbers->formatsFor($doc, $voucherDate),
            'docType' => $doc,
            'voucherDate' => $voucherDate,
            'menuKey' => self::menuKey($type),
            'editing' => $stockJournal,
            'editItems' => $this->itemsForEdit($stockJournal),
        ]);
    }

    public function update(Request $request, StockJournal $stockJournal): RedirectResponse
    {
        $this->authorizeJournalAccess($request, $stockJournal);
        abort_unless($stockJournal->status === 'active', 404);
        $type = $stockJournal->type;
        abort_unless($request->user()->canMenu(self::menuKey($type), 'edit'), 403);

        $data = $this->validated($request, $type);
        $this->assertActiveFyDate($data['journal_date'], 'journal_date');
        $godownId = (int) $data['godown_id'];
        $this->authorizeGodown($request, $godownId);
        if ($type === 'transfer') {
            $this->authorizeGodown($request, (int) $data['to_godown_id']);
        }
        foreach ($data['items'] as $row) {
            $this->authorizeGodown($request, (int) ($row['godown_id'] ?? $godownId));
        }

        try {
            $journal = $this->journals->update($stockJournal, $data + ['type' => $type], $request->user()->id);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('stock-journals.show', $journal)
            ->with('status', __(':type :no updated.', ['type' => __(self::META[$type][0]), 'no' => $journal->journal_no]));
    }

    public function cancel(Request $request, StockJournal $stockJournal): RedirectResponse
    {
        $this->authorizeJournalAccess($request, $stockJournal);
        abort_unless($request->user()->canMenu(self::menuKey($stockJournal->type), 'delete'), 403);

        try {
            $this->journals->cancel($stockJournal);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', __('Voucher cancelled.'));
    }

    private function authorizeJournalAccess(Request $request, StockJournal $journal): void
    {
        $allowed = $request->user()->accessibleGodownIds();
        $ok = in_array((int) $journal->godown_id, $allowed, true)
            || ($journal->to_godown_id && in_array((int) $journal->to_godown_id, $allowed, true));
        abort_unless($ok, 403, __('You do not have access to this godown.'));
    }

    private function validated(Request $request, string $type): array
    {
        $rules = [
            'journal_date' => ['required', 'date'],
            'document_series_id' => ['nullable', 'integer', 'exists:document_series,id'],
            'godown_id' => ['required', 'integer'],
            'to_godown_id' => [$type === 'transfer' ? 'required' : 'nullable', 'integer', 'different:godown_id'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.godown_id' => ['nullable', 'integer'],
            'items.*.batch_id' => ['nullable', 'exists:product_batches,id'],
            'items.*.batch_no' => ['nullable', 'string', 'max:60'],
            'items.*.mfg_date' => ['nullable', 'date'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.side' => ['nullable', 'in:in,out'],
            'items.*.qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.counted_qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.rate' => ['nullable', 'numeric', 'min:0'],
        ];

        return $request->validate($rules);
    }

    /** @return array<int, array<string, mixed>> */
    private function itemsForEdit(StockJournal $journal): array
    {
        $lines = $journal->lines()->with('product')->get();

        if ($journal->type === 'transfer') {
            $lines = $lines->where('side', 'out')->values();
        }

        return $lines->map(function ($line) use ($journal) {
            return [
                'godown_id' => (string) ($line->godown_id ?: $journal->godown_id),
                'side' => $line->side === 'count' ? 'count' : ($line->side === 'in' ? 'in' : 'out'),
                'product_id' => (string) $line->product_id,
                'batch_id' => $line->batch_id ? (string) $line->batch_id : '',
                'batch_no' => $line->batch_no ?? '',
                'qty' => $journal->type === 'physical' ? '' : (string) $line->qty,
                'counted_qty' => $line->counted_qty !== null ? (string) $line->counted_qty : (string) $line->qty,
                'rate' => (float) $line->rate,
            ];
        })->all();
    }
}
