<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rack / aisle locations (put-away after receive) + optional place per sale/purchase line.
 * Stock qty stays SUM by product+godown+batch — rack is location only, not a stock bucket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('racks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('godown_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->string('name_mr')->nullable();
            $table->string('aisle', 40)->nullable();
            $table->string('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['godown_id', 'code']);
            $table->index(['godown_id', 'is_active']);
        });

        Schema::table('product_batches', function (Blueprint $table) {
            if (! Schema::hasColumn('product_batches', 'rack_id')) {
                $table->foreignId('rack_id')->nullable()->after('godown_id')->constrained('racks')->nullOnDelete();
            }
        });

        Schema::table('sale_items', function (Blueprint $table) {
            if (! Schema::hasColumn('sale_items', 'godown_id')) {
                $table->foreignId('godown_id')->nullable()->after('sale_id')->constrained('godowns')->nullOnDelete();
            }
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_items', 'godown_id')) {
                $table->foreignId('godown_id')->nullable()->after('purchase_id')->constrained('godowns')->nullOnDelete();
            }
            if (! Schema::hasColumn('purchase_items', 'rack_id')) {
                $table->foreignId('rack_id')->nullable()->after('batch_id')->constrained('racks')->nullOnDelete();
            }
        });

        // Backfill line places from header vouchers (portable for MySQL + SQLite tests)
        if (Schema::hasColumn('sale_items', 'godown_id')) {
            DB::table('sale_items')->whereNull('godown_id')->orderBy('id')->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $gid = DB::table('sales')->where('id', $row->sale_id)->value('godown_id');
                    if ($gid) {
                        DB::table('sale_items')->where('id', $row->id)->update(['godown_id' => $gid]);
                    }
                }
            });
        }
        if (Schema::hasColumn('purchase_items', 'godown_id')) {
            DB::table('purchase_items')->whereNull('godown_id')->orderBy('id')->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $gid = DB::table('purchases')->where('id', $row->purchase_id)->value('godown_id');
                    if ($gid) {
                        DB::table('purchase_items')->where('id', $row->id)->update(['godown_id' => $gid]);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_items', 'rack_id')) {
                $table->dropConstrainedForeignId('rack_id');
            }
            if (Schema::hasColumn('purchase_items', 'godown_id')) {
                $table->dropConstrainedForeignId('godown_id');
            }
        });
        Schema::table('sale_items', function (Blueprint $table) {
            if (Schema::hasColumn('sale_items', 'godown_id')) {
                $table->dropConstrainedForeignId('godown_id');
            }
        });
        Schema::table('product_batches', function (Blueprint $table) {
            if (Schema::hasColumn('product_batches', 'rack_id')) {
                $table->dropConstrainedForeignId('rack_id');
            }
        });
        Schema::dropIfExists('racks');
    }
};
