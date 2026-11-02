<?php

namespace App\Models;

use App\Models\Concerns\LocalizedName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Godown extends Model
{
    use LocalizedName;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function racks(): HasMany
    {
        return $this->hasMany(Rack::class);
    }

    public function defaultRack(): BelongsTo
    {
        return $this->belongsTo(Rack::class, 'default_rack_id');
    }
}
