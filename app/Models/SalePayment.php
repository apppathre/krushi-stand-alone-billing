<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['payment_date' => 'date', 'amount' => 'decimal:2'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }
}
