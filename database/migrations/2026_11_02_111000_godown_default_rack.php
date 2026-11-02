<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('godowns', function (Blueprint $table) {
            if (! Schema::hasColumn('godowns', 'default_rack_id')) {
                $table->foreignId('default_rack_id')->nullable()->after('address')
                    ->constrained('racks')->nullOnDelete();
            }
        });

        Schema::table('racks', function (Blueprint $table) {
            if (! Schema::hasColumn('racks', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('godowns', function (Blueprint $table) {
            if (Schema::hasColumn('godowns', 'default_rack_id')) {
                $table->dropConstrainedForeignId('default_rack_id');
            }
        });
        Schema::table('racks', function (Blueprint $table) {
            if (Schema::hasColumn('racks', 'is_default')) {
                $table->dropColumn('is_default');
            }
        });
    }
};
