<?php

namespace App\Models;

use App\Models\Concerns\LocalizedName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rack extends Model
{
    use LocalizedName;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function godown(): BelongsTo
    {
        return $this->belongsTo(Godown::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function getLabelAttribute(): string
    {
        $bits = array_filter([$this->aisle, $this->code, $this->display_name]);

        return implode(' · ', $bits) ?: (string) $this->code;
    }

    public function getGodownLabelAttribute(): string
    {
        return $this->godown?->display_name ?? '';
    }

    public function getDefaultLabelAttribute(): string
    {
        return $this->is_default ? 'Yes' : '';
    }
}
