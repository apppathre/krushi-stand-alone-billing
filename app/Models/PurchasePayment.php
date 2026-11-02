<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchasePayment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['payment_date' => 'date', 'amount' => 'decimal:2'];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }
}
