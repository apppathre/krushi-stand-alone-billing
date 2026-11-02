<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bill due = grand_total − paid_amount − returned_amount.
 * Keeps receipts/payments from over-settling after credit/debit notes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'returned_amount')) {
                $table->decimal('returned_amount', 14, 2)->default(0)->after('paid_amount');
            }
        });
        Schema::table('purchases', function (Blueprint $table) {
            if (! Schema::hasColumn('purchases', 'returned_amount')) {
                $table->decimal('returned_amount', 14, 2)->default(0)->after('paid_amount');
            }
        });

        // Backfill from existing return docs (portable across MySQL / SQLite tests).
        if (Schema::hasTable('sale_returns')) {
            $sums = DB::table('sale_returns')
                ->select('sale_id', DB::raw('ROUND(SUM(grand_total), 2) as total'))
                ->groupBy('sale_id')
                ->get();
            foreach ($sums as $row) {
                DB::table('sales')->where('id', $row->sale_id)->update([
                    'returned_amount' => (float) $row->total,
                ]);
            }
        }
        if (Schema::hasTable('purchase_returns')) {
            $sums = DB::table('purchase_returns')
                ->select('purchase_id', DB::raw('ROUND(SUM(grand_total), 2) as total'))
                ->groupBy('purchase_id')
                ->get();
            foreach ($sums as $row) {
                DB::table('purchases')->where('id', $row->purchase_id)->update([
                    'returned_amount' => (float) $row->total,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'returned_amount')) {
                $table->dropColumn('returned_amount');
            }
        });
        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'returned_amount')) {
                $table->dropColumn('returned_amount');
            }
        });
    }
};
