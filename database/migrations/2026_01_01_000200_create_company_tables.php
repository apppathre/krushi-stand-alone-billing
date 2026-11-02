<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profile', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_mr')->nullable();
            $table->string('tagline')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state_code', 2)->default('27');
            $table->string('state_name')->default('Maharashtra');
            $table->string('pincode', 10)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('gstin', 15)->nullable();
            $table->string('pan', 10)->nullable();
            $table->string('seed_licence_no')->nullable();
            $table->string('fertilizer_licence_no')->nullable();
            $table->string('pesticide_licence_no')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_no')->nullable();
            $table->string('bank_ifsc')->nullable();
            $table->text('invoice_terms')->nullable();
            $table->timestamps();
        });

        Schema::create('mail_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->default(587);
            $table->string('encryption', 10)->nullable()->default('tls');
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('from_address')->nullable();
            $table->string('from_name')->nullable();
            $table->timestamps();
        });

        Schema::create('godowns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_mr')->nullable();
            $table->string('code', 20)->nullable()->unique();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_godowns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('godown_id')->constrained()->cascadeOnDelete();
            $table->unique(['user_id', 'godown_id']);
        });

        Schema::create('financial_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        Schema::create('document_series', function (Blueprint $table) {
            $table->id();
            $table->string('doc_type', 30); // sale, purchase, credit_note
            $table->foreignId('financial_year_id')->constrained()->cascadeOnDelete();
            $table->string('prefix', 20);
            $table->unsignedInteger('next_number')->default(1);
            $table->unsignedTinyInteger('padding')->default(4);
            $table->timestamps();
            $table->unique(['doc_type', 'financial_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_series');
        Schema::dropIfExists('financial_years');
        Schema::dropIfExists('user_godowns');
        Schema::dropIfExists('godowns');
        Schema::dropIfExists('mail_settings');
        Schema::dropIfExists('company_profile');
    }
};
