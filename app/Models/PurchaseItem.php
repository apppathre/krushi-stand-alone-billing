<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['expiry_date' => 'date', 'mfg_date' => 'date'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }

    public function godown(): BelongsTo
    {
        return $this->belongsTo(Godown::class);
    }

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    public function getReturnableQtyAttribute(): float
    {
        return round((float) $this->qty - (float) ($this->returned_qty ?? 0), 3);
    }
}
