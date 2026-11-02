<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountVoucherLine extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(AccountVoucher::class, 'account_voucher_id');
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }
}
