<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Godown;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppControlAndGstTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! User::where('email', \Database\Seeders\UserSeeder::OWNER_EMAIL)->exists()) {
            $this->seed();
        }
    }

    public function test_app_control_defaults_apply_to_new_client_and_gst_report_renders(): void
    {
        $owner = User::where('email', \Database\Seeders\UserSeeder::OWNER_EMAIL)->firstOrFail();
        $this->actingAs($owner);

        $this->put(route('settings.app-control.update'), [
            'default_credit_days' => 30,
            'default_credit_limit' => 0,
            'default_client_opening_type' => 'dr',
            'default_supplier_opening_type' => 'cr',
            'default_commission_pct' => 2,
            'commission_enabled' => 1,
            'enforce_credit_limit' => 0,
            'default_invoice_print_format' => 'standard',
            'invoice_print_lang' => 'en',
            'default_print_size' => 'a4',
            'live_stock_default_view' => 'table',
            'live_stock_theme' => 'day',
            'commission_expense_ledger_id' => CompanyProfile::current()->id ? \App\Models\AccountMap::current()->commission_expense_ledger_id : null,
            'commission_payable_ledger_id' => \App\Models\AccountMap::current()->commission_payable_ledger_id,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $company = CompanyProfile::current()->fresh();
        $this->assertSame(30, (int) $company->default_credit_days);
        $this->assertTrue((bool) $company->commission_enabled);

        $this->get(route('clients.create', ['drawer' => 1]))->assertOk()
            ->assertSee('name="credit_days"', false)
            ->assertSee('value="30"', false)
            ->assertSee('value="dr"', false);

        $this->get(route('suppliers.create', ['drawer' => 1]))->assertOk()
            ->assertSee('value="cr"', false);

        $godown = Godown::first();
        $supplier = Ledger::where('type', 'supplier')->first();
        $client = Ledger::where('type', 'client')->where('id', '!=', \App\Models\AccountMap::current()->walk_in_ledger_id)->first();
        $product = Product::where('batch_required', true)->first();

        $this->post(route('purchases.store'), [
            'godown_id' => $godown->id,
            'supplier_ledger_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id, 'batch_no' => 'G1',
                'expiry_date' => now()->addYear()->toDateString(),
                'qty' => 5, 'rate' => 100, 'discount_pct' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        $batchId = \App\Models\ProductBatch::where('batch_no', 'G1')->value('id');
        $this->post(route('sales.store'), [
            'godown_id' => $godown->id,
            'client_ledger_id' => $client->id,
            'invoice_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'batch_id' => $batchId, 'qty' => 1, 'rate' => 150, 'discount_pct' => 0]],
        ])->assertSessionHasNoErrors();

        $this->get(route('reports.gst-return'))->assertOk()
            ->assertSee('B2B', false)
            ->assertSee('HSN', false);
    }
}
