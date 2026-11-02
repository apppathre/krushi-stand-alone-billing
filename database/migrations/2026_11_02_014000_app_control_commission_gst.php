<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profile', function (Blueprint $table) {
            if (! Schema::hasColumn('company_profile', 'default_credit_days')) {
                $table->unsignedSmallInteger('default_credit_days')->default(30)->after('invoice_terms');
            }
            if (! Schema::hasColumn('company_profile', 'default_credit_limit')) {
                $table->decimal('default_credit_limit', 14, 2)->default(0)->after('default_credit_days');
            }
            if (! Schema::hasColumn('company_profile', 'default_client_opening_type')) {
                $table->string('default_client_opening_type', 2)->default('dr')->after('default_credit_limit');
            }
            if (! Schema::hasColumn('company_profile', 'default_supplier_opening_type')) {
                $table->string('default_supplier_opening_type', 2)->default('cr')->after('default_client_opening_type');
            }
            if (! Schema::hasColumn('company_profile', 'commission_enabled')) {
                $table->boolean('commission_enabled')->default(false)->after('default_supplier_opening_type');
            }
            if (! Schema::hasColumn('company_profile', 'default_commission_pct')) {
                $table->decimal('default_commission_pct', 5, 2)->default(0)->after('commission_enabled');
            }
            if (! Schema::hasColumn('company_profile', 'enforce_credit_limit')) {
                $table->boolean('enforce_credit_limit')->default(false)->after('default_commission_pct');
            }
            if (! Schema::hasColumn('company_profile', 'default_print_size')) {
                $table->string('default_print_size', 20)->default('a4')->after('invoice_print_lang');
            }
        });

        Schema::table('account_maps', function (Blueprint $table) {
            if (! Schema::hasColumn('account_maps', 'commission_expense_ledger_id')) {
                $table->foreignId('commission_expense_ledger_id')->nullable()->after('discount_ledger_id')
                    ->constrained('ledgers')->nullOnDelete();
            }
            if (! Schema::hasColumn('account_maps', 'commission_payable_ledger_id')) {
                $table->foreignId('commission_payable_ledger_id')->nullable()->after('commission_expense_ledger_id')
                    ->constrained('ledgers')->nullOnDelete();
            }
        });

        if (! Schema::hasColumn('sales', 'commission_amount')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->decimal('commission_amount', 14, 2)->default(0)->after('paid_amount');
                $table->foreignId('salesperson_user_id')->nullable()->after('commission_amount')
                    ->constrained('users')->nullOnDelete();
                $table->decimal('commission_pct', 5, 2)->nullable()->after('salesperson_user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'salesperson_user_id')) {
                $table->dropConstrainedForeignId('salesperson_user_id');
            }
            foreach (['commission_pct', 'commission_amount'] as $col) {
                if (Schema::hasColumn('sales', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::table('account_maps', function (Blueprint $table) {
            if (Schema::hasColumn('account_maps', 'commission_payable_ledger_id')) {
                $table->dropConstrainedForeignId('commission_payable_ledger_id');
            }
            if (Schema::hasColumn('account_maps', 'commission_expense_ledger_id')) {
                $table->dropConstrainedForeignId('commission_expense_ledger_id');
            }
        });
        Schema::table('company_profile', function (Blueprint $table) {
            foreach ([
                'default_print_size', 'enforce_credit_limit', 'default_commission_pct', 'commission_enabled',
                'default_supplier_opening_type', 'default_client_opening_type', 'default_credit_limit', 'default_credit_days',
            ] as $col) {
                if (Schema::hasColumn('company_profile', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
