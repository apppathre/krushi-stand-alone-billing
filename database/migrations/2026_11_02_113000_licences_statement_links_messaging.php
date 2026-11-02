<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profile', function (Blueprint $table) {
            if (! Schema::hasColumn('company_profile', 'shop_act_licence_no')) {
                $table->string('shop_act_licence_no', 120)->nullable()->after('pesticide_licence_no');
            }
            if (! Schema::hasColumn('company_profile', 'shop_act_valid_till')) {
                $table->date('shop_act_valid_till')->nullable()->after('shop_act_licence_no');
            }
            if (! Schema::hasColumn('company_profile', 'fssai_licence_no')) {
                $table->string('fssai_licence_no', 120)->nullable()->after('shop_act_valid_till');
            }
            if (! Schema::hasColumn('company_profile', 'fssai_valid_till')) {
                $table->date('fssai_valid_till')->nullable()->after('fssai_licence_no');
            }
            if (! Schema::hasColumn('company_profile', 'seed_licence_valid_till')) {
                $table->date('seed_licence_valid_till')->nullable()->after('seed_licence_no');
            }
            if (! Schema::hasColumn('company_profile', 'fertilizer_licence_valid_till')) {
                $table->date('fertilizer_licence_valid_till')->nullable()->after('fertilizer_licence_no');
            }
            if (! Schema::hasColumn('company_profile', 'pesticide_licence_valid_till')) {
                $table->date('pesticide_licence_valid_till')->nullable()->after('pesticide_licence_no');
            }
            if (! Schema::hasColumn('company_profile', 'upi_id')) {
                $table->string('upi_id', 100)->nullable()->after('bank_branch');
            }
            if (! Schema::hasColumn('company_profile', 'outstanding_wa_template')) {
                $table->text('outstanding_wa_template')->nullable()->after('invoice_terms');
            }
            if (! Schema::hasColumn('company_profile', 'statement_link_enabled')) {
                $table->boolean('statement_link_enabled')->default(true)->after('outstanding_wa_template');
            }
            if (! Schema::hasColumn('company_profile', 'statement_link_days')) {
                $table->unsignedSmallInteger('statement_link_days')->default(7)->after('statement_link_enabled');
            }
            if (! Schema::hasColumn('company_profile', 'statement_allow_payment')) {
                $table->boolean('statement_allow_payment')->default(true)->after('statement_link_days');
            }
        });

        if (! Schema::hasTable('party_statement_tokens')) {
            Schema::create('party_statement_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ledger_id')->constrained('ledgers')->cascadeOnDelete();
                $table->string('token', 64)->unique();
                $table->timestamp('expires_at')->nullable()->index();
                $table->boolean('include_bills')->default(true);
                $table->boolean('allow_payment')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('last_opened_at')->nullable();
                $table->unsignedInteger('open_count')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('party_statement_tokens');

        Schema::table('company_profile', function (Blueprint $table) {
            foreach ([
                'shop_act_licence_no', 'shop_act_valid_till', 'fssai_licence_no', 'fssai_valid_till',
                'seed_licence_valid_till', 'fertilizer_licence_valid_till', 'pesticide_licence_valid_till',
                'upi_id', 'outstanding_wa_template', 'statement_link_enabled', 'statement_link_days',
                'statement_allow_payment',
            ] as $col) {
                if (Schema::hasColumn('company_profile', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
