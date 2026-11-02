<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'supplier_invoice_date' => 'date',
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
        return $this->hasMany(PurchaseItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'supplier_ledger_id');
    }

    public function godown(): BelongsTo
    {
        return $this->belongsTo(Godown::class);
    }

    /** Open bill due after payments and purchase returns. */
    public function getBalanceAttribute(): float
    {
        return max(0, round(
            (float) $this->grand_total - (float) $this->paid_amount - (float) ($this->returned_amount ?? 0),
            2
        ));
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
