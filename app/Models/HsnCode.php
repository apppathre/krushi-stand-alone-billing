<?php

namespace App\Models;

use App\Models\Concerns\LocalizedName;
use Illuminate\Database\Eloquent\Model;

class HsnCode extends Model
{
    use LocalizedName;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'gst_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function getLabelAttribute(): string
    {
        return $this->code.' — '.$this->display_name;
    }
}
