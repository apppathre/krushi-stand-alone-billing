<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_type', 40); // payment_voucher | receipt_voucher | journal_voucher
            $table->string('voucher_no', 60);
            $table->foreignId('format_id')->nullable()->constrained('document_series')->nullOnDelete();
            $table->date('voucher_date');
            $table->string('narration', 500)->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->foreignId('godown_id')->nullable()->constrained('godowns')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('active'); // active | cancelled
            $table->timestamps();

            $table->unique(['voucher_type', 'voucher_no']);
            $table->index(['voucher_date', 'voucher_type']);
            $table->index('status');
        });

        Schema::create('account_voucher_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_voucher_id')->constrained('account_vouchers')->cascadeOnDelete();
            $table->foreignId('ledger_id')->constrained('ledgers')->restrictOnDelete();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('narration', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('company_profile', function (Blueprint $table) {
            if (! Schema::hasColumn('company_profile', 'overdue_interest_pct')) {
                $table->decimal('overdue_interest_pct', 8, 2)->default(0)->after('default_credit_days');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_voucher_lines');
        Schema::dropIfExists('account_vouchers');
        Schema::table('company_profile', function (Blueprint $table) {
            if (Schema::hasColumn('company_profile', 'overdue_interest_pct')) {
                $table->dropColumn('overdue_interest_pct');
            }
        });
    }
};
