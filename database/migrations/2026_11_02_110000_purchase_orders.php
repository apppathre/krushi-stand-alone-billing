<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no')->unique();
            $table->foreignId('financial_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('format_id')->nullable()->constrained('document_series')->nullOnDelete();
            $table->date('order_date')->index();
            $table->date('expected_date')->nullable();
            $table->foreignId('godown_id')->constrained();
            $table->foreignId('supplier_ledger_id')->constrained('ledgers');
            $table->string('supply_state_code', 2)->nullable();
            $table->boolean('is_igst')->default(false);
            $table->decimal('taxable_total', 14, 2)->default(0);
            $table->decimal('cgst_total', 14, 2)->default(0);
            $table->decimal('sgst_total', 14, 2)->default(0);
            $table->decimal('igst_total', 14, 2)->default(0);
            $table->decimal('round_off', 8, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->string('notes')->nullable();
            $table->text('terms')->nullable();
            $table->string('status', 20)->default('open'); // open|converted|cancelled
            $table->foreignId('converted_purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'order_date']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('godown_id')->nullable()->constrained()->nullOnDelete();
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
            $table->string('narration')->nullable();
        });

        Schema::table('purchases', function (Blueprint $table) {
            if (! Schema::hasColumn('purchases', 'purchase_order_id')) {
                $table->foreignId('purchase_order_id')->nullable()->after('supplier_ledger_id')
                    ->constrained('purchase_orders')->nullOnDelete();
            }
            if (! Schema::hasColumn('purchases', 'settlement_type')) {
                $table->string('settlement_type', 10)->nullable()->after('paid_amount'); // credit|cash
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'purchase_order_id')) {
                $table->dropConstrainedForeignId('purchase_order_id');
            }
            if (Schema::hasColumn('purchases', 'settlement_type')) {
                $table->dropColumn('settlement_type');
            }
        });
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
