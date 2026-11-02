<?php

namespace App\Models;

use App\Models\Concerns\LocalizedName;
use Illuminate\Database\Eloquent\Model;

class CompanyProfile extends Model
{
    use LocalizedName;

    protected $table = 'company_profile';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'commission_enabled' => 'boolean',
            'enforce_credit_limit' => 'boolean',
            'live_stock_enabled' => 'boolean',
            'live_stock_show_rate' => 'boolean',
            'statement_link_enabled' => 'boolean',
            'statement_allow_payment' => 'boolean',
            'default_credit_limit' => 'decimal:2',
            'default_commission_pct' => 'decimal:2',
            'overdue_interest_pct' => 'decimal:2',
            'shop_act_valid_till' => 'date',
            'fssai_valid_till' => 'date',
            'seed_licence_valid_till' => 'date',
            'fertilizer_licence_valid_till' => 'date',
            'pesticide_licence_valid_till' => 'date',
        ];
    }

    /** Request-memoized company row (created on demand). */
    protected static ?self $currentMemo = null;

    public static function current(): self
    {
        if (static::$currentMemo) {
            return static::$currentMemo;
        }

        return static::$currentMemo = static::query()->first() ?? static::create([
            'name' => config('app.name'),
            'state_code' => '27',
            'state_name' => 'Maharashtra',
            'default_credit_days' => 30,
            'default_credit_limit' => 0,
            'default_client_opening_type' => 'dr',
            'default_supplier_opening_type' => 'cr',
            'default_invoice_print_format' => 'standard',
            'invoice_print_lang' => 'auto',
            'default_print_size' => 'a4',
        ]);
    }

    public static function forgetCurrent(): void
    {
        static::$currentMemo = null;
    }
}
