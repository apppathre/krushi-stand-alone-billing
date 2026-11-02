<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_date' => 'date',
            'is_igst' => 'boolean',
            'grand_total' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'supplier_ledger_id');
    }

    public function godown(): BelongsTo
    {
        return $this->belongsTo(Godown::class);
    }

    public function convertedPurchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'converted_purchase_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
