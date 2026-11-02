<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['return_date' => 'date', 'is_igst' => 'boolean', 'grand_total' => 'decimal:2'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'client_ledger_id');
    }

    public function godown(): BelongsTo
    {
        return $this->belongsTo(Godown::class);
    }
}
