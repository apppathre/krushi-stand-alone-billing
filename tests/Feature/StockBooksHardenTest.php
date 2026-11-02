<?php

namespace Tests\Feature;

use App\Models\Godown;
use App\Models\Ledger;
use App\Models\LedgerEntry;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockJournal;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\StockQty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Stock path harden: transfer, physical up/down, over-issue, cancel restores SUM log + batch cache. */
class StockBooksHardenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! User::where('email', \Database\Seeders\UserSeeder::OWNER_EMAIL)->exists()) {
            $this->seed();
        }
    }

    private function owner(): User
    {
        return User::where('email', \Database\Seeders\UserSeeder::OWNER_EMAIL)->firstOrFail();
    }

    private function assertBatchCacheMatches(ProductBatch $batch): void
    {
        $batch->refresh();
        $this->assertEquals(
            StockQty::freshBatchOnHand($batch->id),
            (float) $batch->qty_on_hand,
            'Batch qty_on_hand cache must match SUM(stock_movements)'
        );
    }

    public function test_godown_transfer_moves_qty_and_cancel_restores(): void
    {
        $this->actingAs($this->owner());
        $from = Godown::firstOrFail();
        $to = Godown::create(['name' => 'Yard B', 'code' => 'YB', 'is_active' => true]);
        $this->owner()->godowns()->syncWithoutDetaching([$from->id, $to->id]);

        $supplier = Ledger::where('type', 'supplier')->firstOrFail();
        $product = Product::where('batch_required', true)->firstOrFail();

        $this->post(route('purchases.store'), [
            'godown_id' => $from->id,
            'supplier_ledger_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'settlement_type' => 'credit',
            'items' => [[
                'godown_id' => $from->id,
                'product_id' => $product->id,
                'batch_no' => 'TR1',
                'expiry_date' => now()->addYear()->toDateString(),
                'qty' => 20,
                'rate' => 40,
                'discount_pct' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        $src = ProductBatch::where('batch_no', 'TR1')->where('godown_id', $from->id)->firstOrFail();
        $this->assertEquals(20.0, StockQty::freshBatchOnHand($src->id));

        $this->post(route('stock-journals.transfer.store'), [
            'godown_id' => $from->id,
            'to_godown_id' => $to->id,
            'journal_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'batch_id' => $src->id,
                'qty' => 7,
            ]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertEquals(13.0, StockQty::freshBatchOnHand($src->id));
        $dest = ProductBatch::where('batch_no', 'TR1')->where('godown_id', $to->id)->firstOrFail();
        $this->assertEquals(7.0, StockQty::freshBatchOnHand($dest->id));
        $this->assertBatchCacheMatches($src);
        $this->assertBatchCacheMatches($dest);

        $journal = StockJournal::where('type', 'transfer')->latest('id')->firstOrFail();
        $this->delete(route('stock-journals.cancel', $journal))->assertRedirect();

        $this->assertEquals(20.0, StockQty::freshBatchOnHand($src->id));
        $this->assertEquals(0.0, StockQty::freshBatchOnHand($dest->id));
        $this->assertBatchCacheMatches($src->fresh());
        $this->assertBatchCacheMatches($dest->fresh());
        $this->assertTrue($journal->fresh()->isCancelled());
    }

    public function test_physical_stock_up_and_down_posts_diff_only(): void
    {
        $this->actingAs($this->owner());
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
                'batch_no' => 'PHY1',
                'expiry_date' => now()->addYear()->toDateString(),
                'qty' => 10,
                'rate' => 50,
                'discount_pct' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        $batch = ProductBatch::where('batch_no', 'PHY1')->firstOrFail();

        // Count down to 8 → out 2
        $this->post(route('stock-journals.physical.store'), [
            'godown_id' => $godown->id,
            'journal_date' => now()->toDateString(),
            'items' => [[
                'godown_id' => $godown->id,
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'counted_qty' => 8,
                'rate' => 50,
            ]],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(8.0, StockQty::freshBatchOnHand($batch->id));

        $downMoves = StockMovement::query()
            ->where('ref_type', 'stock_journal')
            ->where('movement_type', 'physical_stock')
            ->where('batch_id', $batch->id)
            ->where('qty_out', '>', 0)
            ->get();
        $this->assertEquals(2.0, (float) $downMoves->sum('qty_out'));

        // Count up to 11 → in 3
        $this->post(route('stock-journals.physical.store'), [
            'godown_id' => $godown->id,
            'journal_date' => now()->toDateString(),
            'items' => [[
                'godown_id' => $godown->id,
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'counted_qty' => 11,
                'rate' => 50,
            ]],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(11.0, StockQty::freshBatchOnHand($batch->id));
        $this->assertBatchCacheMatches($batch->fresh());
    }

    public function test_issue_over_on_hand_is_rejected_and_stock_return_adds_qty(): void
    {
        $this->actingAs($this->owner());
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
                'batch_no' => 'ISS1',
                'expiry_date' => now()->addYear()->toDateString(),
                'qty' => 5,
                'rate' => 30,
                'discount_pct' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        $batch = ProductBatch::where('batch_no', 'ISS1')->firstOrFail();

        $this->post(route('stock-journals.issue.store'), [
            'godown_id' => $godown->id,
            'journal_date' => now()->toDateString(),
            'items' => [[
                'godown_id' => $godown->id,
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'qty' => 9,
            ]],
        ])->assertSessionHasErrors('items');
        $this->assertEquals(5.0, StockQty::freshBatchOnHand($batch->id));

        $this->post(route('stock-journals.issue.store'), [
            'godown_id' => $godown->id,
            'journal_date' => now()->toDateString(),
            'items' => [[
                'godown_id' => $godown->id,
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'qty' => 2,
            ]],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(3.0, StockQty::freshBatchOnHand($batch->id));

        // Stock return is stock-in (no issued-unused cap in this app) — adds qty back.
        $this->post(route('stock-journals.return.store'), [
            'godown_id' => $godown->id,
            'journal_date' => now()->toDateString(),
            'items' => [[
                'godown_id' => $godown->id,
                'product_id' => $product->id,
                'batch_no' => 'ISS1',
                'qty' => 1,
                'rate' => 30,
            ]],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(4.0, StockQty::freshBatchOnHand($batch->id));
        $this->assertBatchCacheMatches($batch->fresh());
    }

    public function test_sale_and_purchase_cancel_restores_stock_and_clears_ledger(): void
    {
        $this->actingAs($this->owner());
        $godown = Godown::firstOrFail();
        $supplier = Ledger::where('type', 'supplier')->firstOrFail();
        $walkInId = (int) (\App\Models\AccountMap::current()->walk_in_ledger_id ?: 0);
        $client = Ledger::where('type', 'client')->when($walkInId, fn ($q) => $q->where('id', '!=', $walkInId))->firstOrFail();
        $product = Product::where('batch_required', true)->firstOrFail();
        $cashId = Ledger::query()->paymentInstruments()->where('type', 'cash')->value('id');

        $this->post(route('purchases.store'), [
            'godown_id' => $godown->id,
            'supplier_ledger_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'settlement_type' => 'cash',
            'paid_amount' => 500,
            'payment_mode' => 'cash',
            'payment_ledger_id' => $cashId,
            'items' => [[
                'product_id' => $product->id,
                'batch_no' => 'CX1',
                'expiry_date' => now()->addYear()->toDateString(),
                'qty' => 10,
                'rate' => 50,
                'discount_pct' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        $purchase = Purchase::latest('id')->firstOrFail();
        $batch = ProductBatch::where('batch_no', 'CX1')->firstOrFail();
        $this->assertEquals(10.0, StockQty::freshBatchOnHand($batch->id));
        $this->assertGreaterThan(0, LedgerEntry::where('voucher_type', 'purchase')->where('voucher_id', $purchase->id)->count());

        $this->post(route('sales.store'), [
            'godown_id' => $godown->id,
            'client_ledger_id' => $client->id,
            'invoice_date' => now()->toDateString(),
            'settlement_type' => 'credit',
            'paid_amount' => 0,
            'items' => [[
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'qty' => 3,
                'rate' => 80,
                'discount_pct' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        $sale = Sale::latest('id')->firstOrFail();
        $this->assertEquals(7.0, StockQty::freshBatchOnHand($batch->id));
        $this->assertGreaterThan(0, LedgerEntry::where('voucher_type', 'sale')->where('voucher_id', $sale->id)->count());

        $this->delete(route('sales.cancel', $sale))->assertRedirect();
        $this->assertEquals(10.0, StockQty::freshBatchOnHand($batch->id));
        $this->assertSame(0, LedgerEntry::where('voucher_type', 'sale')->where('voucher_id', $sale->id)->count());
        $this->assertTrue($sale->fresh()->isCancelled());

        // Cannot cancel purchase while stock from it is still free — after sale cancel, qty is back.
        $this->delete(route('purchases.cancel', $purchase))->assertRedirect();
        $this->assertEquals(0.0, StockQty::freshBatchOnHand($batch->id));
        $this->assertSame(0, LedgerEntry::where('voucher_type', 'purchase')->where('voucher_id', $purchase->id)->count());
        $this->assertSame(0, LedgerEntry::where('voucher_type', 'purchase_payment')->whereIn(
            'voucher_id',
            $purchase->payments()->pluck('id')
        )->count());
        $this->assertTrue($purchase->fresh()->isCancelled());
        $this->assertBatchCacheMatches($batch->fresh());
    }

    public function test_physical_edit_reposts_once(): void
    {
        $this->actingAs($this->owner());
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
                'batch_no' => 'PED1',
                'expiry_date' => now()->addYear()->toDateString(),
                'qty' => 12,
                'rate' => 20,
                'discount_pct' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        $batch = ProductBatch::where('batch_no', 'PED1')->firstOrFail();

        $this->post(route('stock-journals.physical.store'), [
            'godown_id' => $godown->id,
            'journal_date' => now()->toDateString(),
            'items' => [[
                'godown_id' => $godown->id,
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'counted_qty' => 10,
                'rate' => 20,
            ]],
        ])->assertSessionHasNoErrors();

        $journal = StockJournal::where('type', 'physical')->latest('id')->firstOrFail();
        $this->assertEquals(10.0, StockQty::freshBatchOnHand($batch->id));

        $this->put(route('stock-journals.update', $journal), [
            'godown_id' => $godown->id,
            'journal_date' => now()->toDateString(),
            'items' => [[
                'godown_id' => $godown->id,
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'counted_qty' => 9,
                'rate' => 20,
            ]],
        ])->assertSessionHasNoErrors();

        // Edit must leave counted 9 (diff from original 12), not compound prior physical.
        $this->assertEquals(9.0, StockQty::freshBatchOnHand($batch->id));
        $this->assertBatchCacheMatches($batch->fresh());
    }
}
