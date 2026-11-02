<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'is_igst' => 'boolean',
            'grand_total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'returned_amount' => 'decimal:2',
            'cash_discount' => 'decimal:2',
            'freight_amount' => 'decimal:2',
            'hammali_amount' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'client_ledger_id');
    }

    public function godown(): BelongsTo
    {
        return $this->belongsTo(Godown::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Open bill due after receipts and credit notes. */
    public function getBalanceAttribute(): float
    {
        return max(0, round(
            (float) $this->grand_total - (float) $this->paid_amount - (float) ($this->returned_amount ?? 0),
            2
        ));
    }

    public function getPaymentStatusAttribute(): string
    {
        if ($this->balance <= 0.004) {
            return ((float) $this->paid_amount > 0.004 || (float) ($this->returned_amount ?? 0) > 0.004)
                ? 'paid'
                : 'unpaid';
        }
        if ((float) $this->paid_amount <= 0.004 && (float) ($this->returned_amount ?? 0) <= 0.004) {
            return 'unpaid';
        }

        return 'partial';
    }

    /** SQL filter: still has money due (after paid + returned). */
    public function scopeOpenDue($query)
    {
        return $query->whereRaw('(paid_amount + COALESCE(returned_amount, 0)) < (grand_total - 0.004)');
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
