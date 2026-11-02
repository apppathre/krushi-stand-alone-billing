<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
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

    public function getReturnableQtyAttribute(): float
    {
        return round((float) $this->qty - (float) $this->returned_qty, 3);
    }
}
