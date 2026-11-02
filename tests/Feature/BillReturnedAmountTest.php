<?php

namespace Tests\Feature;

use App\Models\Godown;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Credit note must reduce bill due so receipt cannot over-pay. */
class BillReturnedAmountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! User::where('email', \Database\Seeders\UserSeeder::OWNER_EMAIL)->exists()) {
            $this->seed();
        }
    }

    public function test_credit_note_reduces_invoice_balance_for_receipts(): void
    {
        $owner = User::where('email', \Database\Seeders\UserSeeder::OWNER_EMAIL)->firstOrFail();
        $this->actingAs($owner);

        $godown = Godown::firstOrFail();
        $walkInId = (int) (\App\Models\AccountMap::current()->walk_in_ledger_id ?: 0);
        $client = Ledger::where('type', 'client')->when($walkInId, fn ($q) => $q->where('id', '!=', $walkInId))->firstOrFail();
        $product = Product::where('batch_required', true)->firstOrFail();

        app(\App\Services\StockService::class)->receive([
            'product_id' => $product->id,
            'godown_id' => $godown->id,
            'batch_no' => 'BAL-CN-1',
            'expiry_date' => now()->addYear()->toDateString(),
            'qty' => 50,
            'rate' => 40,
            'type' => 'opening',
            'date' => now()->toDateString(),
        ]);
        $batch = ProductBatch::where('batch_no', 'BAL-CN-1')->firstOrFail();

        /** @var SaleService $sales */
        $sales = app(SaleService::class);
        $sale = $sales->create([
            'invoice_date' => now()->toDateString(),
            'godown_id' => $godown->id,
            'client_ledger_id' => $client->id,
            'paid_amount' => 0,
            'items' => [[
                'godown_id' => $godown->id,
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'qty' => 2,
                'rate' => 100,
                'discount_pct' => 0,
            ]],
        ], $owner->id);

        $sale = $sale->fresh();
        $this->assertGreaterThan(0, (float) $sale->grand_total);
        $full = (float) $sale->balance;

        $item = $sale->items()->firstOrFail();
        $return = $sales->createReturn($sale, [$item->id => 1], now()->toDateString(), 'partial', $owner->id);
        $sale = $sale->fresh();

        $this->assertGreaterThan(0, (float) $sale->returned_amount);
        $this->assertEqualsWithDelta((float) $return->grand_total, (float) $sale->returned_amount, 0.02);
        $this->assertLessThan($full, (float) $sale->balance);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $sales->addPayment($sale, [
            'amount' => $full,
            'payment_date' => now()->toDateString(),
            'mode' => 'cash',
        ], $owner->id);
    }
}
