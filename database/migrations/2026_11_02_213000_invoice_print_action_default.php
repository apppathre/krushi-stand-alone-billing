<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ask = chooser dialog; browser = system print dialog; pdf = open mPDF. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profile', function (Blueprint $table) {
            if (! Schema::hasColumn('company_profile', 'invoice_print_action')) {
                $table->string('invoice_print_action', 20)->default('ask')->after('default_print_size');
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_profile', function (Blueprint $table) {
            if (Schema::hasColumn('company_profile', 'invoice_print_action')) {
                $table->dropColumn('invoice_print_action');
            }
        });
    }
};
