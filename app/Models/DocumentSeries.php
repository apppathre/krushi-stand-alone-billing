<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSeries extends Model
{
    protected $table = 'document_series';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function financialYear()
    {
        return $this->belongsTo(FinancialYear::class);
    }
}
