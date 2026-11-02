<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'show_on_live_stock')) {
                $table->boolean('show_on_live_stock')->default(true)->after('is_active');
            }
        });

        Schema::table('company_profile', function (Blueprint $table) {
            if (! Schema::hasColumn('company_profile', 'live_stock_default_view')) {
                $table->string('live_stock_default_view', 16)->default('table')->after('live_stock_show_rate');
            }
            if (! Schema::hasColumn('company_profile', 'live_stock_theme')) {
                $table->string('live_stock_theme', 16)->default('day')->after('live_stock_default_view');
            }
            if (! Schema::hasColumn('company_profile', 'live_stock_on_dashboard')) {
                $table->boolean('live_stock_on_dashboard')->default(false)->after('live_stock_theme');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'show_on_live_stock')) {
                $table->dropColumn('show_on_live_stock');
            }
        });

        Schema::table('company_profile', function (Blueprint $table) {
            foreach (['live_stock_on_dashboard', 'live_stock_theme', 'live_stock_default_view'] as $col) {
                if (Schema::hasColumn('company_profile', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
