<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fast SUM totals from the single stock / money logs.
 * Also rebuilds product_batches.qty_on_hand from stock_movements (cache only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['product_id', 'godown_id', 'batch_id'], 'stock_movements_bucket_idx');
            $table->index(['batch_id', 'movement_date'], 'stock_movements_batch_date_idx');
        });

        // Keep the denormalized cache aligned with the move log.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('
                UPDATE product_batches
                SET qty_on_hand = COALESCE((
                    SELECT SUM(sm.qty_in - sm.qty_out)
                    FROM stock_movements sm
                    WHERE sm.batch_id = product_batches.id
                ), 0)
            ');
        } else {
            DB::statement('
                UPDATE product_batches pb
                SET qty_on_hand = COALESCE((
                    SELECT SUM(sm.qty_in - sm.qty_out)
                    FROM stock_movements sm
                    WHERE sm.batch_id = pb.id
                ), 0)
            ');
        }
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('stock_movements_bucket_idx');
            $table->dropIndex('stock_movements_batch_date_idx');
        });
    }
};
