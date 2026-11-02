<?php

namespace Tests\Feature;

use App\Models\AccountVoucher;
use App\Models\Godown;
use App\Models\Ledger;
use App\Models\LedgerEntry;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockJournal;
use App\Models\User;
use App\Support\StockQty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-5 UAT checklist as an automated smoke (same path staff walk in the browser).
 * Opening → Purchase → Transfer → Issue → Return → Physical → Rejection → Journal
 * → Sale (partial pay) → Credit note → Receipt → Purchase return → JV
 * → Stock list + party ledger + day book + books:reconcile.
 */
class UatStockAccountSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! User::where('email', \Database\Seeders\UserSeeder::OWNER_EMAIL)->exists()) {
            $this->seed();
        }
    }

    public function test_full_stock_and_account_uat_path(): void
    {
        $owner = User::where('email', \Database\Seeders\UserSeeder::OWNER_EMAIL)->firstOrFail();
        $this->actingAs($owner);

        $from = Godown::firstOrFail();
        $to = Godown::create(['name' => 'UAT Yard', 'code' => 'UAT', 'is_active' => true]);
        $owner->godowns()->syncWithoutDetaching([$from->id, $to->id]);

        $supplier = Ledger::where('type', 'supplier')->firstOrFail();
        $walkInId = (int) (\App\Models\AccountMap::current()->walk_in_ledger_id ?: 0);
        $client = Ledger::where('type', 'client')->when($walkInId, fn ($q) => $q->where('id', '!=', $walkInId))->firstOrFail();
        $product = Product::where('batch_required', true)->firstOrFail();
        $cash = Ledger::query()->paymentInstruments()->where('type', 'cash')->firstOrFail();
        $expense = Ledger::books()->where('type', 'expense')->firstOrFail();
        $date = now()->toDateString();

        // 1. Opening
        $this->post(route('opening-stock.store'), [
            'movement_date' => $date,
            'godown_id' => $from->id,
            'items' => [[
                'product_id' => $product->id,
                'batch_no' => 'UAT-OPEN',
                'expiry_date' => now()->addYear()->toDateString(),
                'qty' => 5,
                'rate' => 40,
            ]],
        ])->assertSessionHasNoErrors();

        // 2. Purchase
        $this->post(route('purchases.store'), [
            'godown_id' => $from->id,
            'supplier_ledger_id' => $supplier->id,
            'purchase_date' => $date,
            'settlement_type' => 'credit',
            'items' => [[
                'godown_id' => $from->id,
                'product_id' => $product->id,
                'batch_no' => 'UAT-1',
                'expiry_date' => now()->addYear()->toDateString(),
                'qty' => 30,
                'rate' => 50,
                'discount_pct' => 0,
            ]],
        ])->assertSessionHasNoErrors();
        $batch = ProductBatch::where('batch_no', 'UAT-1')->where('godown_id', $from->id)->firstOrFail();
        $this->assertEquals(30.0, StockQty::freshBatchOnHand($batch->id));

        // 3. Transfer 4 to other godown
        $this->post(route('stock-journals.transfer.store'), [
            'godown_id' => $from->id,
            'to_godown_id' => $to->id,
            'journal_date' => $date,
            'items' => [['product_id' => $product->id, 'batch_id' => $batch->id, 'qty' => 4]],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(26.0, StockQty::freshBatchOnHand($batch->id));

        // 4. Issue 3
        $this->post(route('stock-journals.issue.store'), [
            'godown_id' => $from->id,
            'journal_date' => $date,
            'items' => [['godown_id' => $from->id, 'product_id' => $product->id, 'batch_id' => $batch->id, 'qty' => 3]],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(23.0, StockQty::freshBatchOnHand($batch->id));

        // 5. Stock return 1
        $this->post(route('stock-journals.return.store'), [
            'godown_id' => $from->id,
            'journal_date' => $date,
            'items' => [['godown_id' => $from->id, 'product_id' => $product->id, 'batch_no' => 'UAT-1', 'qty' => 1, 'rate' => 50]],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(24.0, StockQty::freshBatchOnHand($batch->id));

        // 6. Physical to 22
        $this->post(route('stock-journals.physical.store'), [
            'godown_id' => $from->id,
            'journal_date' => $date,
            'items' => [[
                'godown_id' => $from->id,
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'counted_qty' => 22,
                'rate' => 50,
            ]],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(22.0, StockQty::freshBatchOnHand($batch->id));

        // 7. Rejection 1
        $this->post(route('stock-journals.rejection.store'), [
            'godown_id' => $from->id,
            'journal_date' => $date,
            'items' => [['product_id' => $product->id, 'batch_id' => $batch->id, 'qty' => 1]],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(21.0, StockQty::freshBatchOnHand($batch->id));

        // 8. Journal consume 1 + produce 1
        $this->post(route('stock-journals.journal.store'), [
            'godown_id' => $from->id,
            'journal_date' => $date,
            'items' => [
                ['side' => 'out', 'product_id' => $product->id, 'batch_id' => $batch->id, 'qty' => 1],
                [
                    'side' => 'in',
                    'product_id' => $product->id,
                    'batch_no' => 'UAT-2',
                    'expiry_date' => now()->addYear()->toDateString(),
                    'qty' => 1,
                    'rate' => 50,
                ],
            ],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(20.0, StockQty::freshBatchOnHand($batch->id));

        // 9. Sale partial pay
        $this->post(route('sales.store'), [
            'godown_id' => $from->id,
            'client_ledger_id' => $client->id,
            'invoice_date' => $date,
            'settlement_type' => 'cash',
            'paid_amount' => 100,
            'payment_mode' => 'cash',
            'payment_ledger_id' => $cash->id,
            'items' => [[
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'qty' => 2,
                'rate' => 120,
                'discount_pct' => 0,
            ]],
        ])->assertSessionHasNoErrors();
        $sale = Sale::latest('id')->firstOrFail();
        $this->assertEquals(18.0, StockQty::freshBatchOnHand($batch->id));

        // 10. Credit note 1
        $this->post(route('sale-returns.store'), [
            'sale_id' => $sale->id,
            'return_date' => $date,
            'reason' => 'UAT',
            'qty' => [$sale->items()->first()->id => 1],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(19.0, StockQty::freshBatchOnHand($batch->id));

        // 11. Extra receipt if balance remains
        $sale->refresh();
        if ($sale->balance > 0.05) {
            $pay = min(50, (float) $sale->balance);
            $this->post(route('sales.payments.store', $sale), [
                'amount' => $pay,
                'mode' => 'cash',
                'ledger_id' => $cash->id,
                'payment_date' => $date,
            ])->assertSessionHasNoErrors();
        }

        // 12. Purchase return 2
        $purchase = Purchase::latest('id')->firstOrFail();
        $this->post(route('purchase-returns.store'), [
            'purchase_id' => $purchase->id,
            'return_date' => $date,
            'qty' => [$purchase->items()->first()->id => 2],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(17.0, StockQty::freshBatchOnHand($batch->id));

        // 13. Journal voucher
        $this->post(route('account-vouchers.journal.store'), [
            'voucher_date' => $date,
            'amount' => 25,
            'debit_ledger_id' => $expense->id,
            'credit_ledger_id' => $cash->id,
            'narration' => 'UAT JV',
        ])->assertSessionHasNoErrors();

        // Screens
        $this->get(route('stock.index'))->assertOk();
        $this->get(route('reports.party-ledger', ['ledger_id' => $client->id]))->assertOk();
        $this->get(route('reports.day-book'))->assertOk();
        $this->get(route('reports.trial-balance'))->assertOk();

        // Invariants
        $totals = LedgerEntry::query()
            ->selectRaw('COALESCE(SUM(debit),0) as dr, COALESCE(SUM(credit),0) as cr')
            ->first();
        $this->assertEquals(round((float) $totals->dr, 2), round((float) $totals->cr, 2));

        $batch->refresh();
        $this->assertEquals(StockQty::freshBatchOnHand($batch->id), (float) $batch->qty_on_hand);
        $this->assertGreaterThanOrEqual(5, StockJournal::count());
        $this->assertTrue(AccountVoucher::where('narration', 'UAT JV')->exists());

        $this->artisan('books:reconcile')->assertSuccessful();
    }
}
