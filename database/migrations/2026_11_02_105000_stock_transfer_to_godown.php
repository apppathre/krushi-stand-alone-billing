<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_journals', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_journals', 'to_godown_id')) {
                $table->foreignId('to_godown_id')->nullable()->after('godown_id')
                    ->constrained('godowns')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_journals', function (Blueprint $table) {
            if (Schema::hasColumn('stock_journals', 'to_godown_id')) {
                $table->dropConstrainedForeignId('to_godown_id');
            }
        });
    }
};
