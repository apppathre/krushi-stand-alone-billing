<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReturn extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'is_igst' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'supplier_ledger_id');
    }

    public function godown(): BelongsTo
    {
        return $this->belongsTo(Godown::class);
    }
}
