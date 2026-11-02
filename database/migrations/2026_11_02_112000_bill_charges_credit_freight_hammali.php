<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['sales', 'purchases'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'credit_days')) {
                    $table->unsignedSmallInteger('credit_days')->nullable()->after('notes');
                }
                if (! Schema::hasColumn($tableName, 'due_date')) {
                    $table->date('due_date')->nullable()->after('credit_days');
                }
                if (! Schema::hasColumn($tableName, 'cash_discount')) {
                    $table->decimal('cash_discount', 14, 2)->default(0)->after('discount_total');
                }
                if (! Schema::hasColumn($tableName, 'freight_amount')) {
                    $table->decimal('freight_amount', 14, 2)->default(0)->after('cash_discount');
                }
                if (! Schema::hasColumn($tableName, 'hammali_amount')) {
                    $table->decimal('hammali_amount', 14, 2)->default(0)->after('freight_amount');
                }
            });
        }

        Schema::table('account_maps', function (Blueprint $table) {
            if (! Schema::hasColumn('account_maps', 'freight_ledger_id')) {
                $table->foreignId('freight_ledger_id')->nullable()->after('discount_ledger_id')
                    ->constrained('ledgers')->nullOnDelete();
            }
            if (! Schema::hasColumn('account_maps', 'hammali_ledger_id')) {
                $table->foreignId('hammali_ledger_id')->nullable()->after('freight_ledger_id')
                    ->constrained('ledgers')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('account_maps', function (Blueprint $table) {
            if (Schema::hasColumn('account_maps', 'hammali_ledger_id')) {
                $table->dropConstrainedForeignId('hammali_ledger_id');
            }
            if (Schema::hasColumn('account_maps', 'freight_ledger_id')) {
                $table->dropConstrainedForeignId('freight_ledger_id');
            }
        });

        foreach (['sales', 'purchases'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (['hammali_amount', 'freight_amount', 'cash_discount', 'due_date', 'credit_days'] as $col) {
                    if (Schema::hasColumn($tableName, $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
