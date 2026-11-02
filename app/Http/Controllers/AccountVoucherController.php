<?php

namespace App\Http\Controllers;

use App\Models\AccountVoucher;
use App\Models\Ledger;
use App\Models\User;
use App\Services\AccountVoucherService;
use App\Services\FinancialYearService;
use App\Services\InvoiceNumberGenerator;
use App\Support\WorkContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountVoucherController extends Controller
{
    /** @var array<string, array{type:string,menu:string,title:string,blurb:string}> */
    private const KINDS = [
        'payment' => [
            'type' => 'payment_voucher',
            'menu' => 'account_payments',
            'title' => 'Payment voucher',
            'blurb' => 'Pay rent, salary, electricity, transport and other charges from cash/bank.',
        ],
        'receipt' => [
            'type' => 'receipt_voucher',
            'menu' => 'account_receipts',
            'title' => 'Receipt voucher',
            'blurb' => 'Receive other income or party advances (not against a sale bill).',
        ],
        'journal' => [
            'type' => 'journal_voucher',
            'menu' => 'account_journals',
            'title' => 'Journal voucher',
            'blurb' => 'Adjust any two accounts (Dr one, Cr another) — like Tally journal, kept simple.',
        ],
        'contra' => [
            'type' => 'contra_voucher',
            'menu' => 'account_contras',
            'title' => 'Contra voucher',
            'blurb' => 'Move money between cash, bank and UPI only (deposit / withdraw / transfer).',
        ],
    ];

    public function __construct(
        private AccountVoucherService $vouchers,
        private InvoiceNumberGenerator $numbers,
    ) {}

    public function index(Request $request, string $kind)
    {
        $meta = $this->meta($kind);
        abort_unless($request->user()->canMenu($meta['menu'], 'view'), 403);

        $voucherDate = old('voucher_date', app(FinancialYearService::class)->defaultVoucherDate());
        $peek = $this->numbers->peek($meta['type'], $voucherDate);

        $recent = AccountVoucher::with(['lines.ledger', 'staff', 'user'])
            ->where('voucher_type', $meta['type'])
            ->where('status', 'active')
            ->orderByDesc('voucher_date')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return view('vouchers.account-desk', [
            'kind' => $kind,
            'type' => $meta['type'],
            'title' => __($meta['title']),
            'blurb' => __($meta['blurb']),
            'formats' => $peek['formats'],
            'previewNo' => $peek['number'],
            'defaultFormatId' => $peek['format_id'],
            'voucherDate' => $voucherDate,
            'cashLedgers' => Ledger::paymentInstrumentOptions(),
            'accountLedgers' => match ($kind) {
                'payment' => $this->paymentChargeOptions(),
                'receipt' => $this->receiptCreditOptions(),
                'journal' => $this->allLedgerOptions(),
                'contra' => Ledger::paymentInstrumentOptions(),
                default => [],
            },
            'staff' => $kind === 'payment'
                ? User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : collect(),
            'recent' => $recent,
            'placeId' => WorkContext::placeId($request),
            'places' => $request->user()->accessibleGodowns(),
        ]);
    }

    public function store(Request $request, string $kind): RedirectResponse
    {
        $meta = $this->meta($kind);
        abort_unless($request->user()->canMenu($meta['menu'], 'add'), 403);

        $base = [
            'voucher_date' => ['required', 'date'],
            'format_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'narration' => ['nullable', 'string', 'max:500'],
            'godown_id' => ['nullable', 'integer'],
        ];

        $data = match ($kind) {
            'payment' => $request->validate($base + [
                'cash_ledger_id' => Ledger::paymentInstrumentRule(true),
                'account_ledger_id' => ['required', 'integer', Rule::exists('ledgers', 'id')->where('is_active', true)],
                'staff_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            ]),
            'receipt' => $request->validate($base + [
                'cash_ledger_id' => Ledger::paymentInstrumentRule(true),
                'account_ledger_id' => ['required', 'integer', Rule::exists('ledgers', 'id')->where('is_active', true)],
            ]),
            'journal' => $request->validate($base + [
                'debit_ledger_id' => ['required', 'integer', Rule::exists('ledgers', 'id')->where('is_active', true)],
                'credit_ledger_id' => ['required', 'integer', Rule::exists('ledgers', 'id')->where('is_active', true)],
            ]),
            'contra' => $request->validate($base + [
                'from_ledger_id' => Ledger::paymentInstrumentRule(true),
                'to_ledger_id' => Ledger::paymentInstrumentRule(true),
            ]),
            default => abort(404),
        };

        $this->assertActiveFyDate($data['voucher_date'], 'voucher_date');
        if (! empty($data['godown_id'])) {
            abort_unless(in_array((int) $data['godown_id'], $request->user()->accessibleGodownIds(), true), 403);
        }

        try {
            $voucher = match ($kind) {
                'payment' => $this->vouchers->createPayment($data, $request->user()->id),
                'receipt' => $this->vouchers->createReceipt($data, $request->user()->id),
                'journal' => $this->vouchers->createJournal($data, $request->user()->id),
                'contra' => $this->vouchers->createContra($data, $request->user()->id),
            };
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()
            ->route('account-vouchers.'.$kind)
            ->with('status', __('Voucher :no saved.', ['no' => $voucher->voucher_no]));
    }

    public function register(Request $request)
    {
        abort_unless(
            $request->user()->canMenu('account_payments', 'view')
            || $request->user()->canMenu('account_receipts', 'view')
            || $request->user()->canMenu('account_journals', 'view')
            || $request->user()->canMenu('account_contras', 'view'),
            403
        );

        $type = $request->input('type');
        $allowed = ['payment_voucher', 'receipt_voucher', 'journal_voucher', 'contra_voucher'];

        $rows = AccountVoucher::with(['lines.ledger', 'user', 'staff'])
            ->when(in_array($type, $allowed, true), fn ($q) => $q->where('voucher_type', $type))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('voucher_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('voucher_date', '<=', $request->to))
            ->orderByDesc('voucher_date')
            ->orderByDesc('id')
            ->paginate(40)
            ->withQueryString();

        return view('vouchers.account-register', [
            'rows' => $rows,
            'type' => $type,
            'typeOptions' => [
                'payment_voucher' => __('Payment voucher'),
                'receipt_voucher' => __('Receipt voucher'),
                'journal_voucher' => __('Journal voucher'),
                'contra_voucher' => __('Contra voucher'),
            ],
        ]);
    }

    public function cancel(Request $request, AccountVoucher $accountVoucher): RedirectResponse
    {
        $menu = match ($accountVoucher->voucher_type) {
            'payment_voucher' => 'account_payments',
            'receipt_voucher' => 'account_receipts',
            'journal_voucher' => 'account_journals',
            'contra_voucher' => 'account_contras',
            default => 'account_payments',
        };
        abort_unless($request->user()->canMenu($menu, 'delete') || $request->user()->canMenu($menu, 'add'), 403);

        try {
            $this->vouchers->cancel($accountVoucher);
        } catch (\Throwable $e) {
            return back()->withErrors(['voucher' => $e->getMessage()]);
        }

        return back()->with('status', __('Voucher cancelled.'));
    }

    /** @return array{type:string,menu:string,title:string,blurb:string} */
    private function meta(string $kind): array
    {
        abort_unless(isset(self::KINDS[$kind]), 404);

        return self::KINDS[$kind];
    }

    private function paymentChargeOptions(): array
    {
        $expense = Ledger::books()->where('is_active', true)
            ->whereIn('type', ['expense', 'other'])
            ->orderBy('name')->get();
        $parties = Ledger::query()->where('family', 'party')->where('is_active', true)
            ->orderBy('name')->limit(300)->get();

        $opts = [];
        foreach ($expense as $l) {
            $opts[$l->id] = $l->display_name.' · '.__('Expense');
        }
        foreach ($parties as $l) {
            $opts[$l->id] = $l->display_name.' · '.__('Party');
        }

        return $opts;
    }

    private function receiptCreditOptions(): array
    {
        $income = Ledger::books()->where('is_active', true)
            ->whereIn('type', ['income', 'other', 'capital'])
            ->orderBy('name')->get();
        $parties = Ledger::query()->where('family', 'party')->where('is_active', true)
            ->orderBy('name')->limit(300)->get();

        $opts = [];
        foreach ($income as $l) {
            $opts[$l->id] = $l->display_name.' · '.__('Income');
        }
        foreach ($parties as $l) {
            $opts[$l->id] = $l->display_name.' · '.__('Party');
        }

        return $opts;
    }

    private function allLedgerOptions(): array
    {
        return Ledger::query()->where('is_active', true)
            ->orderByRaw("CASE family WHEN 'account' THEN 0 ELSE 1 END")
            ->orderBy('type')->orderBy('name')
            ->get()
            ->mapWithKeys(function (Ledger $l) {
                $tag = $l->family === 'party' ? __('Party') : ucfirst($l->type);

                return [$l->id => $l->display_name.' · '.$tag];
            })->all();
    }
}
