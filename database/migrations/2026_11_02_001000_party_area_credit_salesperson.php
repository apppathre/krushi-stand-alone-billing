<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_mr')->nullable();
            $table->string('short_code', 20)->nullable()->unique();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('ledgers', function (Blueprint $table) {
            $table->unsignedSmallInteger('credit_days')->nullable()->after('credit_limit');
            $table->foreignId('area_id')->nullable()->after('village')->constrained('areas')->nullOnDelete();
            $table->foreignId('salesperson_user_id')->nullable()->after('area_id')->constrained('users')->nullOnDelete();
            $table->decimal('commission_pct', 5, 2)->nullable()->after('salesperson_user_id');
            $table->index('area_id');
        });
    }

    public function down(): void
    {
        Schema::table('ledgers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('salesperson_user_id');
            $table->dropConstrainedForeignId('area_id');
            $table->dropColumn(['credit_days', 'commission_pct']);
        });
        Schema::dropIfExists('areas');
    }
};
