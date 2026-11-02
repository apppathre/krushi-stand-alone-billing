<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manufacturers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_mr')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_mr')->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_mr')->nullable();
            $table->string('short_name', 10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->nullable()->unique();
            $table->string('name');
            $table->string('name_mr')->nullable();
            $table->foreignId('category_id')->constrained('categories');
            $table->foreignId('manufacturer_id')->nullable()->constrained('manufacturers')->nullOnDelete();
            $table->foreignId('unit_id')->constrained('units');
            $table->string('hsn_code', 12)->nullable();
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->decimal('purchase_rate', 12, 2)->default(0);
            $table->decimal('sale_rate', 12, 2)->default(0);
            $table->decimal('mrp', 12, 2)->default(0);
            $table->boolean('batch_required')->default(true);
            $table->decimal('reorder_level', 12, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('name');
        });

        Schema::create('inventory_maps', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 10); // category | product
            $table->foreignId('category_id')->nullable()->constrained('categories')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnDelete();
            $table->foreignId('sales_ledger_id')->nullable()->constrained('ledgers')->nullOnDelete();
            $table->foreignId('purchase_ledger_id')->nullable()->constrained('ledgers')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('godown_id')->constrained();
            $table->string('batch_no', 60);
            $table->date('mfg_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('purchase_rate', 12, 2)->default(0);
            $table->decimal('mrp', 12, 2)->default(0);
            $table->decimal('qty_on_hand', 14, 3)->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'godown_id', 'batch_no']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->date('movement_date')->index();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('godown_id')->constrained();
            $table->foreignId('batch_id')->nullable()->constrained('product_batches')->nullOnDelete();
            // opening | purchase | sale | sale_return | adjustment | reversal
            $table->string('movement_type', 20);
            $table->decimal('qty_in', 14, 3)->default(0);
            $table->decimal('qty_out', 14, 3)->default(0);
            $table->decimal('rate', 12, 2)->default(0); // cost rate
            $table->string('ref_type', 30)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->string('narration')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['ref_type', 'ref_id']);
            $table->index(['product_id', 'godown_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('product_batches');
        Schema::dropIfExists('inventory_maps');
        Schema::dropIfExists('products');
        Schema::dropIfExists('units');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('manufacturers');
    }
};
