<?php

namespace App\Services;

use App\Models\AccountVoucher;
use App\Models\AccountVoucherLine;
use App\Models\Ledger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountVoucherService
{
    public function __construct(
        private InvoiceNumberGenerator $numbers,
        private LedgerPoster $poster,
    ) {}

    /**
     * Simple payment: Dr charge/party ledger, Cr cash/bank.
     * Covers rent, electricity, salary, misc charges, supplier advance (party).
     */
    public function createPayment(array $data, int $userId): AccountVoucher
    {
        return $this->createTwoSided(
            type: 'payment_voucher',
            date: $data['voucher_date'],
            amount: (float) $data['amount'],
            debitLedgerId: (int) $data['account_ledger_id'],
            creditLedgerId: (int) $data['cash_ledger_id'],
            narration: $data['narration'] ?? null,
            formatId: isset($data['format_id']) ? (int) $data['format_id'] : null,
            godownId: isset($data['godown_id']) ? (int) $data['godown_id'] : null,
            userId: $userId,
            staffUserId: isset($data['staff_user_id']) ? (int) $data['staff_user_id'] : null,
        );
    }

    /**
     * Simple receipt (non-sale): Dr cash/bank, Cr income/party.
     */
    public function createReceipt(array $data, int $userId): AccountVoucher
    {
        return $this->createTwoSided(
            type: 'receipt_voucher',
            date: $data['voucher_date'],
            amount: (float) $data['amount'],
            debitLedgerId: (int) $data['cash_ledger_id'],
            creditLedgerId: (int) $data['account_ledger_id'],
            narration: $data['narration'] ?? null,
            formatId: isset($data['format_id']) ? (int) $data['format_id'] : null,
            godownId: isset($data['godown_id']) ? (int) $data['godown_id'] : null,
            userId: $userId,
            staffUserId: null,
        );
    }

    /** Journal: Dr any ledger, Cr any other ledger (adjustments, transfers between heads). */
    public function createJournal(array $data, int $userId): AccountVoucher
    {
        return $this->createTwoSided(
            type: 'journal_voucher',
            date: $data['voucher_date'],
            amount: (float) $data['amount'],
            debitLedgerId: (int) $data['debit_ledger_id'],
            creditLedgerId: (int) $data['credit_ledger_id'],
            narration: $data['narration'] ?? null,
            formatId: isset($data['format_id']) ? (int) $data['format_id'] : null,
            godownId: isset($data['godown_id']) ? (int) $data['godown_id'] : null,
            userId: $userId,
            staffUserId: null,
        );
    }

    /** Contra: move money between cash / bank / UPI only. */
    public function createContra(array $data, int $userId): AccountVoucher
    {
        $from = (int) $data['from_ledger_id'];
        $to = (int) $data['to_ledger_id'];
        $this->assertPaymentInstrument($from, 'from_ledger_id');
        $this->assertPaymentInstrument($to, 'to_ledger_id');

        return $this->createTwoSided(
            type: 'contra_voucher',
            date: $data['voucher_date'],
            amount: (float) $data['amount'],
            debitLedgerId: $to,   // money into
            creditLedgerId: $from, // money out of
            narration: $data['narration'] ?? __('Contra'),
            formatId: isset($data['format_id']) ? (int) $data['format_id'] : null,
            godownId: isset($data['godown_id']) ? (int) $data['godown_id'] : null,
            userId: $userId,
            staffUserId: null,
        );
    }

    /** Journal: Dr party (interest), Cr interest income. */
    public function createInterestJournal(array $data, int $userId): AccountVoucher
    {
        return $this->createJournal([
            'voucher_date' => $data['voucher_date'],
            'amount' => $data['amount'],
            'debit_ledger_id' => $data['party_ledger_id'],
            'credit_ledger_id' => $data['income_ledger_id'],
            'narration' => $data['narration'] ?? __('Overdue interest'),
            'format_id' => $data['format_id'] ?? null,
        ], $userId);
    }

    public function cancel(AccountVoucher $voucher): void
    {
        if ($voucher->isCancelled()) {
            throw ValidationException::withMessages(['voucher' => __('Already cancelled.')]);
        }

        DB::transaction(function () use ($voucher) {
            $this->poster->unpost($voucher->voucher_type, $voucher->id);
            $voucher->update(['status' => 'cancelled']);
        });
    }

    /**
     * Multi-line account voucher (used by Tally XML import).
     *
     * @param  array{voucher_type:string,voucher_date:string,narration?:?string,format_id?:?int,godown_id?:?int,lines:list<array{ledger_id:int,debit:float,credit:float,narration?:?string}>}  $data
     */
    public function createMultiLine(array $data, ?int $userId = null): AccountVoucher
    {
        $type = $data['voucher_type'] ?? 'journal_voucher';
        $allowed = ['payment_voucher', 'receipt_voucher', 'journal_voucher', 'contra_voucher'];
        if (! in_array($type, $allowed, true)) {
            $type = 'journal_voucher';
        }

        $lines = $data['lines'] ?? [];
        if (count($lines) < 2) {
            throw ValidationException::withMessages(['lines' => __('Add at least two ledger lines.')]);
        }

        $dr = round(array_sum(array_map(fn ($l) => (float) ($l['debit'] ?? 0), $lines)), 2);
        $cr = round(array_sum(array_map(fn ($l) => (float) ($l['credit'] ?? 0), $lines)), 2);
        if (abs($dr - $cr) > 0.05 || $dr < 0.005) {
            throw ValidationException::withMessages([
                'lines' => __('Voucher is out of balance (Dr :dr, Cr :cr).', ['dr' => $dr, 'cr' => $cr]),
            ]);
        }

        return DB::transaction(function () use ($data, $type, $lines, $dr, $userId) {
            $date = $data['voucher_date'];
            $formatId = isset($data['format_id']) ? (int) $data['format_id'] : null;
            if (! $formatId) {
                $formatId = (int) ($this->numbers->peek($type, $date)['format_id'] ?? 0) ?: null;
            }
            $no = $this->numbers->next($type, $date, $formatId);
            $narration = $data['narration'] ?? null;

            $voucher = AccountVoucher::create([
                'voucher_type' => $type,
                'voucher_no' => $no,
                'format_id' => $formatId,
                'voucher_date' => $date,
                'narration' => $narration,
                'amount' => $dr,
                'godown_id' => $data['godown_id'] ?? null,
                'user_id' => $userId,
                'status' => 'active',
            ]);

            foreach (array_values($lines) as $i => $line) {
                AccountVoucherLine::create([
                    'account_voucher_id' => $voucher->id,
                    'ledger_id' => (int) $line['ledger_id'],
                    'debit' => round((float) ($line['debit'] ?? 0), 2),
                    'credit' => round((float) ($line['credit'] ?? 0), 2),
                    'narration' => $line['narration'] ?? $narration,
                    'sort_order' => $i + 1,
                ]);
            }

            $this->poster->postAccountVoucher($voucher->fresh('lines'));

            return $voucher;
        });
    }

    private function assertPaymentInstrument(int $ledgerId, string $field): void
    {
        $ok = Ledger::query()->paymentInstruments()->whereKey($ledgerId)->exists();
        if (! $ok) {
            throw ValidationException::withMessages([
                $field => __('Contra only allows cash / bank / UPI accounts.'),
            ]);
        }
    }

    private function createTwoSided(
        string $type,
        string $date,
        float $amount,
        int $debitLedgerId,
        int $creditLedgerId,
        ?string $narration,
        ?int $formatId,
        ?int $godownId,
        int $userId,
        ?int $staffUserId,
    ): AccountVoucher {
        if ($amount <= 0.004) {
            throw ValidationException::withMessages(['amount' => __('Amount must be greater than zero.')]);
        }
        if ($debitLedgerId === $creditLedgerId) {
            throw ValidationException::withMessages(['account_ledger_id' => __('Debit and credit accounts must differ.')]);
        }

        foreach ([$debitLedgerId, $creditLedgerId] as $lid) {
            if (! Ledger::whereKey($lid)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['account_ledger_id' => __('Choose an active ledger.')]);
            }
        }

        return DB::transaction(function () use ($type, $date, $amount, $debitLedgerId, $creditLedgerId, $narration, $formatId, $godownId, $userId, $staffUserId) {
            if (! $formatId) {
                $formatId = (int) ($this->numbers->peek($type, $date)['format_id'] ?? 0) ?: null;
            }
            $no = $this->numbers->next($type, $date, $formatId);

            $voucher = AccountVoucher::create([
                'voucher_type' => $type,
                'voucher_no' => $no,
                'format_id' => $formatId,
                'voucher_date' => $date,
                'narration' => $narration,
                'amount' => round($amount, 2),
                'godown_id' => $godownId,
                'user_id' => $userId,
                'staff_user_id' => $staffUserId,
                'status' => 'active',
            ]);

            AccountVoucherLine::create([
                'account_voucher_id' => $voucher->id,
                'ledger_id' => $debitLedgerId,
                'debit' => round($amount, 2),
                'credit' => 0,
                'narration' => $narration,
                'sort_order' => 1,
            ]);
            AccountVoucherLine::create([
                'account_voucher_id' => $voucher->id,
                'ledger_id' => $creditLedgerId,
                'debit' => 0,
                'credit' => round($amount, 2),
                'narration' => $narration,
                'sort_order' => 2,
            ]);

            $this->poster->postAccountVoucher($voucher->fresh('lines'));

            return $voucher;
        });
    }
}
