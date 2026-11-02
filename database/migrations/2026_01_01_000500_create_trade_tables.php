<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_no')->unique();
            $table->foreignId('financial_year_id')->nullable()->constrained()->nullOnDelete();
            $table->date('purchase_date')->index();
            $table->string('supplier_invoice_no')->nullable();
            $table->date('supplier_invoice_date')->nullable();
            $table->foreignId('godown_id')->constrained();
            $table->foreignId('supplier_ledger_id')->constrained('ledgers');
            $table->string('supply_state_code', 2)->nullable();
            $table->boolean('is_igst')->default(false);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('taxable_total', 14, 2)->default(0);
            $table->decimal('cgst_total', 14, 2)->default(0);
            $table->decimal('sgst_total', 14, 2)->default(0);
            $table->decimal('igst_total', 14, 2)->default(0);
            $table->decimal('round_off', 8, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->string('status', 12)->default('active'); // active | cancelled
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('batch_id')->nullable()->constrained('product_batches')->nullOnDelete();
            $table->string('batch_no', 60)->nullable();
            $table->date('mfg_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('hsn_code', 12)->nullable();
            $table->decimal('qty', 14, 3);
            $table->decimal('rate', 12, 2);
            $table->decimal('mrp', 12, 2)->default(0);
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('taxable_amount', 14, 2)->default(0);
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->decimal('cgst_amount', 12, 2)->default(0);
            $table->decimal('sgst_amount', 12, 2)->default(0);
            $table->decimal('igst_amount', 12, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
        });

        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->date('payment_date');
            $table->string('mode', 10)->default('cash'); // cash | bank | upi
            $table->foreignId('ledger_id')->constrained('ledgers');
            $table->decimal('amount', 14, 2);
            $table->string('reference')->nullable();
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no')->unique();
            $table->foreignId('financial_year_id')->nullable()->constrained()->nullOnDelete();
            $table->date('invoice_date')->index();
            $table->foreignId('godown_id')->constrained();
            $table->foreignId('client_ledger_id')->constrained('ledgers');
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->string('supply_state_code', 2)->nullable();
            $table->boolean('is_igst')->default(false);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('taxable_total', 14, 2)->default(0);
            $table->decimal('cgst_total', 14, 2)->default(0);
            $table->decimal('sgst_total', 14, 2)->default(0);
            $table->decimal('igst_total', 14, 2)->default(0);
            $table->decimal('round_off', 8, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->string('status', 12)->default('active'); // active | cancelled
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('batch_id')->nullable()->constrained('product_batches')->nullOnDelete();
            $table->string('hsn_code', 12)->nullable();
            $table->decimal('qty', 14, 3);
            $table->decimal('returned_qty', 14, 3)->default(0);
            $table->decimal('rate', 12, 2);
            $table->decimal('cost_rate', 12, 2)->default(0);
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('taxable_amount', 14, 2)->default(0);
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->decimal('cgst_amount', 12, 2)->default(0);
            $table->decimal('sgst_amount', 12, 2)->default(0);
            $table->decimal('igst_amount', 12, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->date('payment_date');
            $table->string('mode', 10)->default('cash');
            $table->foreignId('ledger_id')->constrained('ledgers');
            $table->decimal('amount', 14, 2);
            $table->string('reference')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_no')->unique();
            $table->foreignId('financial_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_id')->constrained();
            $table->foreignId('client_ledger_id')->constrained('ledgers');
            $table->foreignId('godown_id')->constrained();
            $table->date('return_date')->index();
            $table->boolean('is_igst')->default(false);
            $table->decimal('taxable_total', 14, 2)->default(0);
            $table->decimal('cgst_total', 14, 2)->default(0);
            $table->decimal('sgst_total', 14, 2)->default(0);
            $table->decimal('igst_total', 14, 2)->default(0);
            $table->decimal('round_off', 8, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained('sale_items');
            $table->foreignId('product_id')->constrained();
            $table->foreignId('batch_id')->nullable()->constrained('product_batches')->nullOnDelete();
            $table->decimal('qty', 14, 3);
            $table->decimal('rate', 12, 2);
            $table->decimal('taxable_amount', 14, 2)->default(0);
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->decimal('cgst_amount', 12, 2)->default(0);
            $table->decimal('sgst_amount', 12, 2)->default(0);
            $table->decimal('igst_amount', 12, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('purchase_payments');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
    }
};
