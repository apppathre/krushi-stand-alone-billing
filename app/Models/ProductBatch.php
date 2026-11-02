<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Batch identity (product + godown + batch_no). qty_on_hand is a cache of SUM(stock_movements). */
class ProductBatch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'mfg_date' => 'date',
            'expiry_date' => 'date',
            'qty_on_hand' => 'decimal:3',
            'purchase_rate' => 'decimal:2',
            'mrp' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function godown(): BelongsTo
    {
        return $this->belongsTo(Godown::class);
    }

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }
}
