<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales', 'terms')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->longText('terms')->nullable()->after('notes');
            });
        }
        if (! Schema::hasColumn('purchases', 'terms')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->longText('terms')->nullable()->after('notes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales', 'terms')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn('terms');
            });
        }
        if (Schema::hasColumn('purchases', 'terms')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->dropColumn('terms');
            });
        }
    }
};
