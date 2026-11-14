<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales', 'settlement_type')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->string('settlement_type', 10)->nullable()->after('paid_amount'); // credit|cash
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales', 'settlement_type')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn('settlement_type');
            });
        }
    }
};
