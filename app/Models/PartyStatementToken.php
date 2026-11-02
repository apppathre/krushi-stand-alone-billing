<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartyStatementToken extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_opened_at' => 'datetime',
            'include_bills' => 'boolean',
            'allow_payment' => 'boolean',
        ];
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
