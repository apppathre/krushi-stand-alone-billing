<?php

namespace App\Models;

use App\Models\Concerns\LocalizedName;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use LocalizedName;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
