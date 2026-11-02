<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_price_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('field', 40); // sale_rate | mrp | purchase_rate
            $table->decimal('old_value', 14, 2)->nullable();
            $table->decimal('new_value', 14, 2)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::table('company_profile', function (Blueprint $table) {
            if (! Schema::hasColumn('company_profile', 'live_stock_enabled')) {
                $table->boolean('live_stock_enabled')->default(false)->after('overdue_interest_pct');
            }
            if (! Schema::hasColumn('company_profile', 'live_stock_passkey')) {
                $table->string('live_stock_passkey')->nullable()->after('live_stock_enabled');
            }
            if (! Schema::hasColumn('company_profile', 'live_stock_title')) {
                $table->string('live_stock_title', 120)->nullable()->after('live_stock_passkey');
            }
            if (! Schema::hasColumn('company_profile', 'live_stock_refresh_sec')) {
                $table->unsignedSmallInteger('live_stock_refresh_sec')->default(30)->after('live_stock_title');
            }
            if (! Schema::hasColumn('company_profile', 'live_stock_godown_id')) {
                $table->foreignId('live_stock_godown_id')->nullable()->after('live_stock_refresh_sec')
                    ->constrained('godowns')->nullOnDelete();
            }
            if (! Schema::hasColumn('company_profile', 'live_stock_show_rate')) {
                $table->boolean('live_stock_show_rate')->default(false)->after('live_stock_godown_id');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_logs');
        Schema::table('company_profile', function (Blueprint $table) {
            foreach (['live_stock_show_rate', 'live_stock_godown_id', 'live_stock_refresh_sec', 'live_stock_title', 'live_stock_passkey', 'live_stock_enabled'] as $col) {
                if (Schema::hasColumn('company_profile', $col)) {
                    if ($col === 'live_stock_godown_id') {
                        $table->dropConstrainedForeignId('live_stock_godown_id');
                    } else {
                        $table->dropColumn($col);
                    }
                }
            }
        });
    }
};
