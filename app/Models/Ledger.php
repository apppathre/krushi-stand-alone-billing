<?php

namespace App\Models;

use App\Models\Concerns\LocalizedName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;

class Ledger extends Model
{
    use LocalizedName;

    public const PARTY_TYPES = ['client', 'supplier', 'both'];

    public const ACCOUNT_TYPES = [
        'cash', 'bank', 'tax', 'sales', 'purchase', 'expense', 'income', 'round_off', 'discount', 'capital', 'other',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'commission_pct' => 'decimal:2',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'client_ledger_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'supplier_ledger_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_user_id');
    }

    public function scopeClients(Builder $q): Builder
    {
        return $q->where('family', 'party')->whereIn('type', ['client', 'both']);
    }

    public function scopeSuppliers(Builder $q): Builder
    {
        return $q->where('family', 'party')->whereIn('type', ['supplier', 'both']);
    }

    public function scopeBooks(Builder $q): Builder
    {
        return $q->where('family', 'account');
    }

    /** Cash, bank and UPI wallet ledgers used on receipt/payment screens. */
    public function scopePaymentInstruments(Builder $q): Builder
    {
        return $q->books()->whereIn('type', ['cash', 'bank'])->where('is_active', true)
            ->orderByRaw("CASE type WHEN 'cash' THEN 0 ELSE 1 END")
            ->orderBy('name');
    }

    /** id => label for payment ledger pickers (Cash, Bank, GPay, PhonePe…). */
    public static function paymentInstrumentOptions(): array
    {
        return static::query()->paymentInstruments()->get()
            ->mapWithKeys(function (self $l) {
                $kind = $l->type === 'cash' ? __('Cash') : __('Bank / UPI');

                return [$l->id => $l->display_name.' · '.$kind];
            })
            ->all();
    }

    /** Validation rule: active cash/bank account ledger (includes UPI wallets). */
    public static function paymentInstrumentRule(bool $required = true, ?string $without = null): array
    {
        $exists = Rule::exists('ledgers', 'id')->where(fn ($q) => $q
            ->where('family', 'account')
            ->whereIn('type', ['cash', 'bank'])
            ->where('is_active', true));

        if (! $required) {
            return ['nullable', 'integer', $exists];
        }

        return $without
            ? ['required_without:'.$without, 'nullable', 'integer', $exists]
            : ['required', 'integer', $exists];
    }

    /** Signed opening balance: + debit, - credit. */
    public function signedOpening(): float
    {
        $amount = (float) $this->opening_balance;

        return $this->opening_type === 'cr' ? -$amount : $amount;
    }
}
