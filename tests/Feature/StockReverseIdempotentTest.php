<?php

namespace Tests\Feature;

use App\Models\Godown;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockJournal;
use App\Models\User;
use App\Services\StockJournalService;
use App\Services\StockService;
use App\Support\StockQty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Edit/cancel after edit must net reverse — not double-reverse original rows. */
class StockReverseIdempotentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! User::where('email', \Database\Seeders\UserSeeder::OWNER_EMAIL)->exists()) {
            $this->seed();
        }
    }

    public function test_stock_journal_edit_twice_keeps_qty_correct(): void
    {
        $owner = User::where('email', \Database\Seeders\UserSeeder::OWNER_EMAIL)->firstOrFail();
        $this->actingAs($owner);

        $godown = Godown::firstOrFail();
        $product = Product::where('batch_required', true)->firstOrFail();

        /** @var StockService $stock */
        $stock = app(StockService::class);
        $batch = $stock->receive([
            'product_id' => $product->id,
            'godown_id' => $godown->id,
            'batch_no' => 'REV-TEST-1',
            'expiry_date' => now()->addYear()->toDateString(),
            'qty' => 100,
            'rate' => 10,
            'type' => 'opening',
            'date' => now()->toDateString(),
        ]);
        $start = StockQty::freshBatchOnHand($batch->id);
        $this->assertEquals(100.0, $start);

        /** @var StockJournalService $journals */
        $journals = app(StockJournalService::class);
        $journal = $journals->create([
            'type' => 'issue',
            'godown_id' => $godown->id,
            'journal_date' => now()->toDateString(),
            'items' => [[
                'godown_id' => $godown->id,
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'qty' => 10,
                'rate' => 10,
            ]],
        ], $owner->id);

        $this->assertEquals($start - 10, StockQty::freshBatchOnHand($batch->id));

        $journals->update($journal, [
            'godown_id' => $godown->id,
            'journal_date' => now()->toDateString(),
            'items' => [[
                'godown_id' => $godown->id,
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'qty' => 15,
                'rate' => 10,
            ]],
        ], $owner->id);
        $this->assertEquals($start - 15, StockQty::freshBatchOnHand($batch->id));

        $journals->update($journal->fresh(), [
            'godown_id' => $godown->id,
            'journal_date' => now()->toDateString(),
            'items' => [[
                'godown_id' => $godown->id,
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'qty' => 20,
                'rate' => 10,
            ]],
        ], $owner->id);
        // Second edit must leave start - 20 (not corrupt via double reverse).
        $this->assertEquals($start - 20, StockQty::freshBatchOnHand($batch->id));

        $journals->cancel($journal->fresh());
        $this->assertEquals($start, StockQty::freshBatchOnHand($batch->id));
        $this->assertTrue(StockJournal::find($journal->id)->isCancelled());
    }
}
