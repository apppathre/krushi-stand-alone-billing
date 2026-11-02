<?php

namespace Tests\Feature;

use App\Models\Godown;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\Rack;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceAndPutAwayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! User::where('email', 'owner@krushi.local')->exists()) {
            $this->seed();
        }
    }

    private function owner(): User
    {
        return User::where('email', 'owner@krushi.local')->firstOrFail();
    }

    public function test_purchase_receive_then_put_away_and_sale_from_line_place(): void
    {
        $this->actingAs($this->owner());
        $a = Godown::first();
        $b = Godown::create(['name' => 'Store B', 'code' => 'SB', 'is_active' => true]);
        $this->owner()->godowns()->syncWithoutDetaching([$a->id, $b->id]);

        $rack = Rack::create([
            'godown_id' => $b->id,
            'code' => 'A-01',
            'name' => 'Aisle A Rack 1',
            'aisle' => 'A',
            'is_active' => true,
        ]);

        $supplier = Ledger::where('type', 'supplier')->first();
        $walkInId = (int) (\App\Models\AccountMap::current()->walk_in_ledger_id ?: 0);
        $client = Ledger::where('type', 'client')->when($walkInId, fn ($q) => $q->where('id', '!=', $walkInId))->first();
        $product = Product::where('batch_required', true)->first();

        // Receive into place B without rack (put-away later)
        $this->post(route('purchases.store'), [
            'godown_id' => $a->id,
            'supplier_ledger_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'items' => [[
                'godown_id' => $b->id,
                'product_id' => $product->id,
                'batch_no' => 'PB1',
                'expiry_date' => now()->addYear()->toDateString(),
                'qty' => 5,
                'rate' => 80,
                'discount_pct' => 0,
            ]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $purchase = Purchase::latest('id')->firstOrFail();
        $this->assertSame((int) $a->id, (int) $purchase->godown_id);
        $line = $purchase->items()->first();
        $this->assertSame((int) $b->id, (int) $line->godown_id);
        $batch = ProductBatch::findOrFail($line->batch_id);
        $this->assertSame((int) $b->id, (int) $batch->godown_id);
        $this->assertNull($batch->rack_id);

        $this->get(route('put-away.index', ['godown_id' => $b->id, 'placed' => 'no']))->assertOk()
            ->assertSee($product->name, false);

        $this->put(route('put-away.update', $batch), ['rack_id' => $rack->id])
            ->assertRedirect();
        $this->assertSame((int) $rack->id, (int) $batch->fresh()->rack_id);

        $this->post(route('sales.store'), [
            'godown_id' => $a->id,
            'client_ledger_id' => $client->id,
            'invoice_date' => now()->toDateString(),
            'items' => [[
                'godown_id' => $b->id,
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'qty' => 2,
                'rate' => 120,
                'discount_pct' => 0,
            ]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $sale = Sale::latest('id')->firstOrFail();
        $this->assertSame((int) $a->id, (int) $sale->godown_id);
        $this->assertSame((int) $b->id, (int) $sale->items()->first()->godown_id);
    }
}
