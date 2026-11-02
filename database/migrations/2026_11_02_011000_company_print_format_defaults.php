<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profile', function (Blueprint $table) {
            if (! Schema::hasColumn('company_profile', 'default_invoice_print_format')) {
                $table->string('default_invoice_print_format', 30)->default('standard')->after('invoice_terms');
            }
            if (! Schema::hasColumn('company_profile', 'invoice_print_lang')) {
                $table->string('invoice_print_lang', 10)->default('auto')->after('default_invoice_print_format');
            }
            if (! Schema::hasColumn('company_profile', 'bank_branch')) {
                $table->string('bank_branch', 120)->nullable()->after('bank_ifsc');
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_profile', function (Blueprint $table) {
            foreach (['default_invoice_print_format', 'invoice_print_lang', 'bank_branch'] as $col) {
                if (Schema::hasColumn('company_profile', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
