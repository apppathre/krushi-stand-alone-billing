<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class AccountMap extends Model
{
    /** Column => label for the settings screen. */
    public const FIELDS = [
        'cash_ledger_id' => 'Cash ledger',
        'bank_ledger_id' => 'Bank ledger',
        'walk_in_ledger_id' => 'Walk-in customer ledger',
        'sales_ledger_id' => 'Sales ledger',
        'sales_return_ledger_id' => 'Sales return ledger',
        'purchase_ledger_id' => 'Purchase ledger',
        'cgst_output_ledger_id' => 'CGST output ledger',
        'sgst_output_ledger_id' => 'SGST output ledger',
        'igst_output_ledger_id' => 'IGST output ledger',
        'cgst_input_ledger_id' => 'CGST input ledger',
        'sgst_input_ledger_id' => 'SGST input ledger',
        'igst_input_ledger_id' => 'IGST input ledger',
        'round_off_ledger_id' => 'Round-off ledger',
        'discount_ledger_id' => 'Discount ledger',
        'freight_ledger_id' => 'Freight / transport ledger',
        'hammali_ledger_id' => 'Hammali / loading ledger',
        'commission_expense_ledger_id' => 'Commission expense ledger',
        'commission_payable_ledger_id' => 'Commission payable ledger',
    ];

    protected $guarded = [];

    public static function current(): self
    {
        return static::query()->first() ?? static::create([]);
    }

    /** Cash for cash payments; bank for bank/UPI. */
    public function paymentLedgerId(string $mode): int
    {
        return $mode === 'cash' ? $this->require('cash_ledger_id') : $this->require('bank_ledger_id');
    }

    /**
     * Resolve the cash/bank/UPI ledger for a payment.
     * Prefer an explicit payment instrument ledger (GPay, PhonePe, Cash…); else fall back to mode map.
     *
     * @return array{ledger_id:int, mode:string}
     */
    public function resolvePaymentLedger(?int $ledgerId = null, ?string $mode = null): array
    {
        if ($ledgerId) {
            $ledger = Ledger::query()->paymentInstruments()->whereKey($ledgerId)->first();
            if (! $ledger) {
                throw ValidationException::withMessages([
                    'ledger_id' => __('Select a cash, bank or UPI ledger.'),
                ]);
            }

            return [
                'ledger_id' => (int) $ledger->id,
                'mode' => $ledger->type === 'cash' ? 'cash' : 'bank',
            ];
        }

        $mode = in_array($mode, ['cash', 'bank', 'upi'], true) ? $mode : 'cash';

        return [
            'ledger_id' => $this->paymentLedgerId($mode),
            'mode' => $mode,
        ];
    }

    public function defaultPaymentLedgerId(): int
    {
        return $this->require('cash_ledger_id');
    }

    /** Resolve a mapped ledger id or fail with a readable message. */
    public function require(string $column): int
    {
        $id = $this->$column;
        if (! $id) {
            throw new \RuntimeException(__('Account map is incomplete: :field is not set.', [
                'field' => __(self::FIELDS[$column] ?? $column),
            ]));
        }

        return (int) $id;
    }
}
