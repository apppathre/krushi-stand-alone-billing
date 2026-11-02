<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Indexes for common list / shell / stock SUM filters (page navigation). */
return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('menus', 'menus_shell_idx', ['is_active', 'parent_id', 'sort_order']);
        $this->addIndexIfMissing('sales', 'sales_place_status_date_idx', ['godown_id', 'status', 'invoice_date']);
        $this->addIndexIfMissing('sales', 'sales_due_idx', ['status', 'paid_amount', 'grand_total']);
        $this->addIndexIfMissing('purchases', 'purchases_place_status_date_idx', ['godown_id', 'status', 'purchase_date']);
        $this->addIndexIfMissing('purchases', 'purchases_due_idx', ['status', 'paid_amount', 'grand_total']);
        $this->addIndexIfMissing('stock_movements', 'stock_movements_place_date_idx', ['godown_id', 'movement_date']);
        $this->addIndexIfMissing('products', 'products_active_name_idx', ['is_active', 'name']);
        $this->addIndexIfMissing('ledgers', 'ledgers_party_list_idx', ['family', 'type', 'is_active', 'name']);
    }

    public function down(): void
    {
        $this->dropIndexIfExists('menus', 'menus_shell_idx');
        $this->dropIndexIfExists('sales', 'sales_place_status_date_idx');
        $this->dropIndexIfExists('sales', 'sales_due_idx');
        $this->dropIndexIfExists('purchases', 'purchases_place_status_date_idx');
        $this->dropIndexIfExists('purchases', 'purchases_due_idx');
        $this->dropIndexIfExists('stock_movements', 'stock_movements_place_date_idx');
        $this->dropIndexIfExists('products', 'products_active_name_idx');
        $this->dropIndexIfExists('ledgers', 'ledgers_party_list_idx');
    }

    /** @param  list<string>  $columns */
    private function addIndexIfMissing(string $table, string $name, array $columns): void
    {
        if (! Schema::hasTable($table) || Schema::hasIndex($table, $name)) {
            return;
        }
        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
            $blueprint->index($columns, $name);
        });
    }

    private function dropIndexIfExists(string $table, string $name): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasIndex($table, $name)) {
            return;
        }
        Schema::table($table, function (Blueprint $blueprint) use ($name) {
            $blueprint->dropIndex($name);
        });
    }
};
