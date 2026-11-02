<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_journals', function (Blueprint $table) {
            $table->id();
            $table->string('journal_no')->unique();
            $table->foreignId('financial_year_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20); // journal|rejection|issue|physical|return
            $table->date('journal_date')->index();
            $table->foreignId('godown_id')->constrained();
            $table->string('notes')->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['type', 'journal_date']);
        });

        Schema::create('stock_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_journal_id')->constrained()->cascadeOnDelete();
            $table->string('side', 10)->default('out'); // in|out|count
            $table->foreignId('product_id')->constrained();
            $table->foreignId('batch_id')->nullable()->constrained('product_batches')->nullOnDelete();
            $table->string('batch_no', 60)->nullable();
            $table->foreignId('godown_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('qty', 14, 3)->default(0); // posted move qty (absolute)
            $table->decimal('counted_qty', 14, 3)->nullable(); // physical only
            $table->decimal('system_qty', 14, 3)->nullable(); // physical snapshot
            $table->decimal('rate', 12, 2)->default(0);
            $table->string('narration')->nullable();
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_no')->unique();
            $table->foreignId('financial_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_id')->constrained();
            $table->foreignId('supplier_ledger_id')->constrained('ledgers');
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

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->constrained('purchase_items');
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

        if (! Schema::hasColumn('purchase_items', 'returned_qty')) {
            Schema::table('purchase_items', function (Blueprint $table) {
                $table->decimal('returned_qty', 14, 3)->default(0)->after('qty');
            });
        }
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_items', 'returned_qty')) {
                $table->dropColumn('returned_qty');
            }
        });
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('stock_journal_lines');
        Schema::dropIfExists('stock_journals');
    }
};
