<?php

namespace Tests\Feature;

use App\Models\Godown;
use App\Models\Ledger;
use App\Models\LedgerEntry;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\User;
use App\Support\StockQty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BooksReconcileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! User::where('email', \Database\Seeders\UserSeeder::OWNER_EMAIL)->exists()) {
            $this->seed();
        }
    }

    public function test_reconcile_is_clean_after_purchase_wire(): void
    {
        $owner = User::where('email', \Database\Seeders\UserSeeder::OWNER_EMAIL)->firstOrFail();
        $this->actingAs($owner);

        $godown = Godown::firstOrFail();
        $supplier = Ledger::where('type', 'supplier')->firstOrFail();
        $product = Product::where('batch_required', true)->firstOrFail();

        $this->post(route('purchases.store'), [
            'godown_id' => $godown->id,
            'supplier_ledger_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'settlement_type' => 'credit',
            'items' => [[
                'product_id' => $product->id,
                'batch_no' => 'REC1',
                'expiry_date' => now()->addYear()->toDateString(),
                'qty' => 4,
                'rate' => 25,
                'discount_pct' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        $this->artisan('books:reconcile')->assertSuccessful();
    }

    public function test_reconcile_detects_cache_drift_and_fix_restores(): void
    {
        $owner = User::where('email', \Database\Seeders\UserSeeder::OWNER_EMAIL)->firstOrFail();
        $this->actingAs($owner);

        $godown = Godown::firstOrFail();
        $supplier = Ledger::where('type', 'supplier')->firstOrFail();
        $product = Product::where('batch_required', true)->firstOrFail();

        $this->post(route('purchases.store'), [
            'godown_id' => $godown->id,
            'supplier_ledger_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'settlement_type' => 'credit',
            'items' => [[
                'product_id' => $product->id,
                'batch_no' => 'DRIFT1',
                'expiry_date' => now()->addYear()->toDateString(),
                'qty' => 5,
                'rate' => 10,
                'discount_pct' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        $batch = ProductBatch::where('batch_no', 'DRIFT1')->firstOrFail();
        $true = StockQty::freshBatchOnHand($batch->id);
        $batch->qty_on_hand = $true + 3;
        $batch->save();

        $this->artisan('books:reconcile')->assertFailed();

        $this->artisan('books:reconcile', ['--fix' => true])->assertSuccessful();
        $batch->refresh();
        $this->assertEquals($true, (float) $batch->qty_on_hand);
    }

    public function test_reconcile_detects_unbalanced_ledger_voucher(): void
    {
        $cash = Ledger::query()->paymentInstruments()->where('type', 'cash')->firstOrFail();
        $expense = Ledger::books()->where('type', 'expense')->firstOrFail();

        LedgerEntry::create([
            'entry_date' => now()->toDateString(),
            'ledger_id' => $expense->id,
            'debit' => 50,
            'credit' => 0,
            'voucher_type' => 'broken_test',
            'voucher_id' => 999001,
            'voucher_no' => 'BRK-1',
            'narration' => 'Intentional imbalance',
            'financial_year_id' => \App\Models\FinancialYear::current()->id,
        ]);
        LedgerEntry::create([
            'entry_date' => now()->toDateString(),
            'ledger_id' => $cash->id,
            'debit' => 0,
            'credit' => 40,
            'voucher_type' => 'broken_test',
            'voucher_id' => 999001,
            'voucher_no' => 'BRK-1',
            'narration' => 'Intentional imbalance',
            'financial_year_id' => \App\Models\FinancialYear::current()->id,
        ]);

        $this->artisan('books:reconcile')->assertFailed();
    }
}
