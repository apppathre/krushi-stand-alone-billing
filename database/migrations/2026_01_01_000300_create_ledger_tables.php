<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_mr')->nullable();
            // family: party (clients/suppliers) | account (books)
            $table->string('family', 10)->index();
            // type: client|supplier|both|cash|bank|tax|sales|purchase|expense|income|round_off|...
            $table->string('type', 30)->index();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('village')->nullable();
            $table->string('city')->nullable();
            $table->string('state_code', 2)->nullable();
            $table->string('gstin', 15)->nullable();
            $table->string('pan', 10)->nullable();
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->string('opening_type', 2)->default('dr'); // dr | cr
            $table->decimal('credit_limit', 14, 2)->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('account_maps', function (Blueprint $table) {
            $table->id();
            foreach ([
                'cash_ledger_id', 'bank_ledger_id', 'walk_in_ledger_id',
                'sales_ledger_id', 'purchase_ledger_id',
                'sales_return_ledger_id',
                'cgst_output_ledger_id', 'sgst_output_ledger_id', 'igst_output_ledger_id',
                'cgst_input_ledger_id', 'sgst_input_ledger_id', 'igst_input_ledger_id',
                'round_off_ledger_id', 'discount_ledger_id',
            ] as $col) {
                $table->foreignId($col)->nullable()->constrained('ledgers')->nullOnDelete();
            }
            $table->timestamps();
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date')->index();
            $table->string('voucher_type', 30); // sale, sale_receipt, purchase, purchase_payment, sale_return
            $table->unsignedBigInteger('voucher_id');
            $table->string('voucher_no')->nullable();
            $table->foreignId('ledger_id')->constrained('ledgers');
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('narration')->nullable();
            $table->foreignId('financial_year_id')->nullable()->constrained('financial_years')->nullOnDelete();
            $table->unsignedBigInteger('godown_id')->nullable();
            $table->timestamps();
            $table->index(['voucher_type', 'voucher_id']);
            $table->index(['ledger_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('account_maps');
        Schema::dropIfExists('ledgers');
    }
};
