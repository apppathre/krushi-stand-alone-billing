<?php

namespace Tests\Feature;

use App\Models\Godown;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
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

    public function test_pages_render_for_owner(): void
    {
        $this->actingAs($this->owner());

        $routes = [
            'dashboard', 'clients.index', 'clients.create', 'suppliers.index', 'suppliers.create', 'books.index',
            'areas.index', 'areas.create',
            'manufacturers.index', 'manufacturers.create', 'categories.index', 'units.index', 'godowns.index',
            'hsn-codes.index', 'gst-slabs.index',
            'products.index', 'products.create', 'products.bulk', 'products.live-board', 'opening-stock.index', 'opening-stock.bulk',
            'put-away.index', 'racks.index',
            'stock-journals.transfer', 'stock-journals.journal', 'stock-journals.rejection', 'stock-journals.issue',
            'stock-journals.physical', 'stock-journals.return',
            'purchase-orders.index', 'purchases.index', 'purchase-returns.index', 'purchase-returns.create',
            'sales.index', 'sale-returns.index', 'sale-returns.create',
            'receipts.index', 'bulk-receipts.create', 'payments.index', 'bulk-payments.create',
            'account-vouchers.payment', 'account-vouchers.receipt', 'account-vouchers.journal', 'account-vouchers.contra',
            'account-vouchers.register',
            'reports.party-ledger', 'reports.stock', 'reports.price-list', 'stock.preview', 'reports.outstanding',
            'reports.day-book', 'reports.cash-book', 'reports.trial-balance', 'reports.balance-sheet',
            'reports.sales-register', 'reports.purchase-register', 'reports.profit-loss',
            'financial-years.index', 'document-series.index',
            'settings.company', 'settings.app-control', 'settings.mail', 'settings.account-maps', 'settings.inventory-maps',
            'reports.gst-return', 'reports.gst-exception', 'reports.tally-export',
            'users.index', 'users.create', 'roles.index', 'roles.create', 'menus.index', 'maintenance.index', 'profile.edit',
        ];

        foreach ($routes as $name) {
            foreach (['en', 'mr'] as $loc) {
                $r = $this->withSession(['locale' => $loc])->get(route($name));
                $this->assertSame(200, $r->status(), $name.' ['.$loc.'] -> '.$r->status().' '.$r->headers->get('Location'));
            }
        }

        $this->get(route('users.edit', $this->owner()))->assertOk();
        $this->get(route('roles.edit', 1))->assertOk();
        $this->get(route('menus.edit', 1))->assertOk();
        $this->get(route('products.edit', Product::first()))->assertOk();
        $this->get(route('stock.movements', Product::first()))->assertOk();
    }

    public function test_purchase_sale_pdf_return_and_reports(): void
    {
        $this->actingAs($this->owner());
        $godown = Godown::first();
        $supplier = Ledger::where('type', 'supplier')->first();
        $walkInId = (int) (\App\Models\AccountMap::current()->walk_in_ledger_id ?: 0);
        $client = Ledger::where('type', 'client')->when($walkInId, fn ($q) => $q->where('id', '!=', $walkInId))->first();
        $product = Product::where('batch_required', true)->first();

        $cashId = Ledger::query()->paymentInstruments()->where('type', 'cash')->value('id');

        $this->post(route('purchases.store'), [
            'godown_id' => $godown->id,
            'supplier_ledger_id' => $supplier->id, 'purchase_date' => now()->toDateString(),
            'supplier_invoice_no' => 'S-1',
            'settlement_type' => 'cash',
            'paid_amount' => 100, 'payment_mode' => 'cash', 'payment_ledger_id' => $cashId,
            'items' => [[
                'product_id' => $product->id, 'batch_no' => 'B1', 'expiry_date' => now()->addYear()->toDateString(),
                'qty' => 10, 'rate' => 100, 'discount_pct' => 0,
            ]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->post(route('sales.store'), [
            'godown_id' => $godown->id,
            'client_ledger_id' => $client->id, 'invoice_date' => now()->toDateString(),
            'settlement_type' => 'cash',
            'paid_amount' => 50, 'payment_mode' => 'cash', 'payment_ledger_id' => $cashId,
            'items' => [['product_id' => $product->id, 'batch_id' => \App\Models\ProductBatch::where('batch_no', 'B1')->value('id'), 'qty' => 2, 'rate' => 150, 'discount_pct' => 0]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $sale = Sale::latest('id')->firstOrFail();
        $this->get(route('sales.show', $sale))->assertOk();
        $this->get(route('sales.pdf', $sale))->assertOk();
        $this->get(route('sales.pdf', [$sale, 'format' => 'standard', 'lang' => 'mr']))->assertOk();
        $this->get(route('sales.pdf', [$sale, 'format' => 'agro', 'lang' => 'mr']))->assertOk();
        $this->get(route('sales.dc', $sale))->assertOk();
        $this->get(route('sales.dc', [$sale, 'lang' => 'mr']))->assertOk();
        $this->get(route('sale-returns.create', ['sale_id' => $sale->id]))->assertOk();
        $this->post(route('sale-returns.store'), [
            'sale_id' => $sale->id, 'return_date' => now()->toDateString(), 'reason' => 'Damaged',
            'qty' => [$sale->items()->first()->id => 1],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('sales.payments.store', $sale), [
            'amount' => 10, 'mode' => 'bank', 'payment_date' => now()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->get(route('reports.day-book'))->assertOk();
        $this->get(route('reports.profit-loss'))->assertOk();
        $this->get(route('reports.party-ledger', ['ledger_id' => $client->id]))->assertOk();
        $this->get(route('reports.sales-register'))->assertOk();
        $this->get(route('reports.purchase-register'))->assertOk();
    }
}
