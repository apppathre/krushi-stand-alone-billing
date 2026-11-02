<?php

namespace App\Models;

use App\Models\Concerns\LocalizedName;
use Illuminate\Database\Eloquent\Model;

class GstSlab extends Model
{
    use LocalizedName;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
