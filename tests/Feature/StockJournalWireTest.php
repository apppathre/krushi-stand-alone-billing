<?php

namespace Tests\Feature;

use App\Models\Godown;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockJournal;
use App\Models\User;
use App\Support\StockQty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** One-pass wire: opening → purchase → journals → purchase return → sale save&print path; qty from SUM log. */
class StockJournalWireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! User::where('email', 'owner@krushi.local')->exists()) {
            $this->seed();
        }
    }

    public function test_stock_counts_through_purchase_journals_and_purchase_return(): void
    {
        $owner = User::where('email', 'owner@krushi.local')->firstOrFail();
        $this->actingAs($owner);

        $godown = Godown::first();
        $supplier = Ledger::where('type', 'supplier')->first();
        $walkInId = (int) (\App\Models\AccountMap::current()->walk_in_ledger_id ?: 0);
        $client = Ledger::where('type', 'client')->when($walkInId, fn ($q) => $q->where('id', '!=', $walkInId))->first();
        $product = Product::where('batch_required', true)->first();

        // Opening stock (go-live) — stock in, no money
        $this->post(route('opening-stock.store'), [
            'movement_date' => now()->toDateString(),
            'godown_id' => $godown->id,
            'items' => [[
                'product_id' => $product->id,
                'batch_no' => 'OPEN1',
                'expiry_date' => now()->addYear()->toDateString(),
                'qty' => 3,
                'rate' => 40,
            ]],
        ])->assertSessionHasNoErrors();
        $openBatch = ProductBatch::where('batch_no', 'OPEN1')->firstOrFail();
        $this->assertEquals(3.0, StockQty::freshBatchOnHand($openBatch->id));

        $this->post(route('purchases.store'), [
            'godown_id' => $godown->id,
            'supplier_ledger_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'items' => [[
                'godown_id' => $godown->id,
                'product_id' => $product->id,
                'batch_no' => 'W1',
                'expiry_date' => now()->addYear()->toDateString(),
                'qty' => 20,
                'rate' => 50,
                'discount_pct' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        $batch = ProductBatch::where('batch_no', 'W1')->firstOrFail();
        $this->assertEquals(20.0, StockQty::freshBatchOnHand($batch->id));

        // Issue 5 out
        $this->post(route('stock-journals.issue.store'), [
            'godown_id' => $godown->id,
            'journal_date' => now()->toDateString(),
            'items' => [[
                'godown_id' => $godown->id,
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'qty' => 5,
                'rate' => 50,
            ]],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertEquals(15.0, StockQty::freshBatchOnHand($batch->id));

        // Physical count to 12 → posts out 3
        $this->post(route('stock-journals.physical.store'), [
            'godown_id' => $godown->id,
            'journal_date' => now()->toDateString(),
            'items' => [[
                'godown_id' => $godown->id,
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'counted_qty' => 12,
                'rate' => 50,
            ]],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(12.0, StockQty::freshBatchOnHand($batch->id));

        // Stock return 2 in
        $this->post(route('stock-journals.return.store'), [
            'godown_id' => $godown->id,
            'journal_date' => now()->toDateString(),
            'items' => [[
                'godown_id' => $godown->id,
                'product_id' => $product->id,
                'batch_no' => 'W1',
                'qty' => 2,
                'rate' => 50,
            ]],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(14.0, StockQty::freshBatchOnHand($batch->id));

        // Rejection 1
        $this->post(route('stock-journals.rejection.store'), [
            'godown_id' => $godown->id,
            'journal_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'qty' => 1,
            ]],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(13.0, StockQty::freshBatchOnHand($batch->id));

        // Journal consume 1 + produce 1
        $this->post(route('stock-journals.journal.store'), [
            'godown_id' => $godown->id,
            'journal_date' => now()->toDateString(),
            'items' => [
                [
                    'side' => 'out',
                    'product_id' => $product->id,
                    'batch_id' => $batch->id,
                    'qty' => 1,
                ],
                [
                    'side' => 'in',
                    'product_id' => $product->id,
                    'batch_no' => 'W2',
                    'expiry_date' => now()->addYear()->toDateString(),
                    'qty' => 1,
                    'rate' => 50,
                ],
            ],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(12.0, StockQty::freshBatchOnHand($batch->id));
        $w2 = ProductBatch::where('batch_no', 'W2')->firstOrFail();
        $this->assertEquals(1.0, StockQty::freshBatchOnHand($w2->id));

        $purchase = Purchase::latest('id')->firstOrFail();
        $item = $purchase->items()->first();
        $this->post(route('purchase-returns.store'), [
            'purchase_id' => $purchase->id,
            'return_date' => now()->toDateString(),
            'qty' => [$item->id => 2],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertEquals(10.0, StockQty::freshBatchOnHand($batch->id));

        // Sale with Save & Print → PDF
        $this->post(route('sales.store'), [
            'godown_id' => $godown->id,
            'client_ledger_id' => $client->id,
            'invoice_date' => now()->toDateString(),
            'after_save' => 'print',
            'items' => [[
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'qty' => 1,
                'rate' => 100,
                'discount_pct' => 0,
            ]],
            'paid_amount' => 0,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $sale = Sale::latest('id')->firstOrFail();
        $this->assertEquals(9.0, StockQty::freshBatchOnHand($batch->id));
        $this->get(route('sales.pdf', $sale))->assertOk();

        $this->assertGreaterThanOrEqual(5, StockJournal::count());

        // Inventory desks + help render
        foreach ([
            route('stock.index'),
            route('opening-stock.index'),
            route('put-away.index'),
            route('stock-journals.journal'),
            route('stock-journals.issue'),
            route('stock-journals.physical'),
            route('stock-journals.return'),
            route('stock-journals.rejection'),
            route('purchase-returns.index'),
            route('sales.index'),
            route('purchases.index'),
        ] as $url) {
            $this->get($url)->assertOk()->assertSee(__('Help'), false);
        }
    }
}
