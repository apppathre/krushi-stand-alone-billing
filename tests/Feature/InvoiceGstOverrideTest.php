<?php

namespace Tests\Feature;

use App\Models\Godown;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceGstOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! User::where('email', \Database\Seeders\UserSeeder::OWNER_EMAIL)->exists()) {
            $this->seed();
        }
    }

    public function test_sale_line_can_override_gst_without_changing_product_master(): void
    {
        $this->actingAs(User::where('email', \Database\Seeders\UserSeeder::OWNER_EMAIL)->firstOrFail());
        $godown = Godown::first();
        $supplier = Ledger::where('type', 'supplier')->first();
        $walkInId = (int) (\App\Models\AccountMap::current()->walk_in_ledger_id ?: 0);
        $client = Ledger::where('type', 'client')->when($walkInId, fn ($q) => $q->where('id', '!=', $walkInId))->first();
        $product = Product::where('batch_required', true)->first();
        $product->update(['gst_rate' => 5]);

        $this->post(route('purchases.store'), [
            'godown_id' => $godown->id,
            'supplier_ledger_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id, 'batch_no' => 'GX',
                'expiry_date' => now()->addYear()->toDateString(),
                'qty' => 10, 'rate' => 100, 'discount_pct' => 0, 'gst_rate' => 5,
            ]],
        ])->assertSessionHasNoErrors();

        $batchId = \App\Models\ProductBatch::where('batch_no', 'GX')->value('id');
        $this->post(route('sales.store'), [
            'godown_id' => $godown->id,
            'client_ledger_id' => $client->id,
            'invoice_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id, 'batch_id' => $batchId,
                'qty' => 2, 'rate' => 100, 'discount_pct' => 0, 'gst_rate' => 18,
            ]],
        ])->assertSessionHasNoErrors();

        $sale = Sale::latest('id')->firstOrFail();
        $this->assertEquals(18.0, (float) $sale->items()->first()->gst_rate);
        $this->assertEquals(5.0, (float) $product->fresh()->gst_rate);
    }
}
