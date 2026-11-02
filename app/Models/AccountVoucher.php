<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountVoucher extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'voucher_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AccountVoucherLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function godown(): BelongsTo
    {
        return $this->belongsTo(Godown::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function format(): BelongsTo
    {
        return $this->belongsTo(DocumentSeries::class, 'format_id');
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
