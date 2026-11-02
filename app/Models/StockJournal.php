<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockJournal extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['journal_date' => 'date'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockJournalLine::class);
    }

    public function godown(): BelongsTo
    {
        return $this->belongsTo(Godown::class);
    }

    /** Destination place for stock transfer (null for other types). */
    public function toGodown(): BelongsTo
    {
        return $this->belongsTo(Godown::class, 'to_godown_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
