<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Godown;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTermsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! User::where('email', \Database\Seeders\UserSeeder::OWNER_EMAIL)->exists()) {
            $this->seed();
        }
    }

    public function test_sale_saves_custom_terms_and_prints_them(): void
    {
        $this->actingAs(User::where('email', \Database\Seeders\UserSeeder::OWNER_EMAIL)->firstOrFail());
        CompanyProfile::current()->update([
            'invoice_terms' => '<p>Shop default terms</p>',
        ]);

        $godown = Godown::first();
        $supplier = Ledger::where('type', 'supplier')->first();
        $walkInId = (int) (\App\Models\AccountMap::current()->walk_in_ledger_id ?: 0);
        $client = Ledger::where('type', 'client')->when($walkInId, fn ($q) => $q->where('id', '!=', $walkInId))->first();
        $product = Product::where('batch_required', true)->first();

        $this->post(route('purchases.store'), [
            'godown_id' => $godown->id,
            'supplier_ledger_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id, 'batch_no' => 'T1',
                'expiry_date' => now()->addYear()->toDateString(),
                'qty' => 5, 'rate' => 100, 'discount_pct' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        $batchId = \App\Models\ProductBatch::where('batch_no', 'T1')->value('id');
        $this->post(route('sales.store'), [
            'godown_id' => $godown->id,
            'client_ledger_id' => $client->id,
            'invoice_date' => now()->toDateString(),
            'terms' => '<p><strong>Custom bill terms</strong> — no return after 7 days.</p>',
            'items' => [[
                'product_id' => $product->id, 'batch_id' => $batchId,
                'qty' => 1, 'rate' => 100, 'discount_pct' => 0,
            ]],
            'paid_amount' => 0,
        ])->assertSessionHasNoErrors();

        $sale = Sale::latest('id')->firstOrFail();
        $this->assertStringContainsString('Custom bill terms', (string) $sale->terms);
        $this->assertStringNotContainsString('Shop default terms', (string) $sale->terms);

        $this->get(route('sales.show', $sale))->assertOk();
        $this->get(route('sales.details', $sale))
            ->assertOk()
            ->assertSee('Custom bill terms', false);

        $html = view('sales.pdf', [
            'sale' => $sale->load(['items.product.unit', 'items.batch', 'client', 'godown', 'payments']),
            'company' => CompanyProfile::current(),
            'printSize' => 'a4',
            'printFormat' => 'standard',
            'printLang' => 'en',
            'partyBalance' => ['amount' => 0.0, 'side' => 'dr', 'label' => '0.00 Dr', 'signed' => 0.0],
        ])->render();
        $this->assertStringContainsString('Custom bill terms', $html);
        $this->assertStringNotContainsString('Shop default terms', $html);
        $this->get(route('sales.pdf', $sale))->assertOk();
    }
}
