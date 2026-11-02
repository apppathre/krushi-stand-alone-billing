<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manufacturers', function (Blueprint $table) {
            $table->string('short_code', 20)->nullable()->after('name_mr');
            $table->unique('short_code');
        });

        Schema::table('company_profile', function (Blueprint $table) {
            $table->string('short_code', 20)->nullable()->after('name_mr');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('marketed_by', 150)->nullable()->after('packing');
            $table->string('usage', 255)->nullable()->after('marketed_by');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['marketed_by', 'usage']);
        });
        Schema::table('company_profile', function (Blueprint $table) {
            $table->dropColumn('short_code');
        });
        Schema::table('manufacturers', function (Blueprint $table) {
            $table->dropUnique(['short_code']);
            $table->dropColumn('short_code');
        });
    }
};
