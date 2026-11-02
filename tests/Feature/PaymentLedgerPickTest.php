<?php

namespace Tests\Feature;

use App\Models\Godown;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentLedgerPickTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! User::where('email', \Database\Seeders\UserSeeder::OWNER_EMAIL)->exists()) {
            $this->seed();
        }
    }

    public function test_receipt_posts_to_selected_upi_ledger(): void
    {
        $this->actingAs(User::where('email', \Database\Seeders\UserSeeder::OWNER_EMAIL)->firstOrFail());
        $godown = Godown::first();
        $supplier = Ledger::where('type', 'supplier')->first();
        $walkInId = (int) (\App\Models\AccountMap::current()->walk_in_ledger_id ?: 0);
        $client = Ledger::where('type', 'client')->when($walkInId, fn ($q) => $q->where('id', '!=', $walkInId))->first();
        $product = Product::where('batch_required', true)->first();
        $gpay = Ledger::firstOrCreate(
            ['name' => 'GPay', 'family' => 'account'],
            ['type' => 'bank', 'is_active' => true, 'is_system' => false],
        );

        $this->post(route('purchases.store'), [
            'godown_id' => $godown->id,
            'supplier_ledger_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id, 'batch_no' => 'PL1',
                'expiry_date' => now()->addYear()->toDateString(),
                'qty' => 5, 'rate' => 100, 'discount_pct' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        $batchId = \App\Models\ProductBatch::where('batch_no', 'PL1')->value('id');
        $this->post(route('sales.store'), [
            'godown_id' => $godown->id,
            'client_ledger_id' => $client->id,
            'invoice_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id, 'batch_id' => $batchId,
                'qty' => 1, 'rate' => 100, 'discount_pct' => 0,
            ]],
            'paid_amount' => 0,
        ])->assertSessionHasNoErrors();

        $sale = Sale::latest('id')->firstOrFail();
        $this->post(route('sales.payments.store', $sale), [
            'amount' => 50,
            'ledger_id' => $gpay->id,
            'payment_date' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $pay = $sale->fresh()->payments()->latest('id')->first();
        $this->assertSame((int) $gpay->id, (int) $pay->ledger_id);
        $this->assertSame('bank', $pay->mode);
    }
}
