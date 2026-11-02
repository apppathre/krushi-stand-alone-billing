<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockJournalLine extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function journal(): BelongsTo
    {
        return $this->belongsTo(StockJournal::class, 'stock_journal_id');
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
}
