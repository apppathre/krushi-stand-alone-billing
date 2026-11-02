<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gst_slabs', function (Blueprint $table) {
            $table->id();
            $table->decimal('rate', 5, 2);
            $table->string('name');
            $table->string('name_mr')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique('rate');
        });

        Schema::create('hsn_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12);
            $table->string('kind', 10)->default('hsn'); // hsn | sac
            $table->string('name');
            $table->string('name_mr')->nullable();
            $table->decimal('gst_rate', 5, 2)->default(0); // default slab hint
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique('code');
            $table->index('kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hsn_codes');
        Schema::dropIfExists('gst_slabs');
    }
};
