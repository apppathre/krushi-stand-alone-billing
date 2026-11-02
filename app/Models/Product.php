<?php

namespace App\Models;

use App\Models\Concerns\LocalizedName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use LocalizedName;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'batch_required' => 'boolean',
            'is_active' => 'boolean',
            'gst_rate' => 'decimal:2',
            'purchase_rate' => 'decimal:2',
            'sale_rate' => 'decimal:2',
            'mrp' => 'decimal:2',
            'reorder_level' => 'decimal:3',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }
}
