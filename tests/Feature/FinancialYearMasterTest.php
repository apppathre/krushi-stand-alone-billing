<?php

namespace Tests\Feature;

use App\Models\DocumentSeries;
use App\Models\FinancialYear;
use App\Models\Godown;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\User;
use App\Services\FinancialYearService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialYearMasterTest extends TestCase
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

    public function test_books_menus_and_pages_render(): void
    {
        $this->actingAs($this->owner());

        foreach (['financial-years.index', 'document-series.index'] as $name) {
            foreach (['en', 'mr'] as $loc) {
                $r = $this->withSession(['locale' => $loc])->get(route($name));
                $this->assertSame(200, $r->status(), $name.' ['.$loc.'] -> '.$r->status());
            }
        }

        $this->get(route('dashboard'))->assertOk()->assertSee(FinancialYear::current()->name, false);
    }

    public function test_create_activate_and_seed_series(): void
    {
        $this->actingAs($this->owner());
        $svc = app(FinancialYearService::class);
        $suggest = $svc->suggest(FinancialYear::orderByDesc('end_date')->first());

        $this->post(route('financial-years.store'), $suggest + ['make_current' => '1'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('financial-years.index'));

        $fy = FinancialYear::where('name', $suggest['name'])->firstOrFail();
        $this->assertTrue($fy->is_current);
        $this->assertGreaterThanOrEqual(3, DocumentSeries::where('financial_year_id', $fy->id)->count());

        $this->get(route('document-series.index', ['financial_year_id' => $fy->id]))
            ->assertOk()
            ->assertSee('Transaction Master', false);
    }

    public function test_sale_rejects_date_outside_active_fy(): void
    {
        $this->actingAs($this->owner());
        $fy = FinancialYear::current();
        $outside = $fy->start_date->copy()->subDay()->toDateString();
        $godown = Godown::first();
        $walkInId = (int) (\App\Models\AccountMap::current()->walk_in_ledger_id ?: 0);
        $client = Ledger::where('type', 'client')->when($walkInId, fn ($q) => $q->where('id', '!=', $walkInId))->first();
        $product = Product::first();

        $this->post(route('sales.store'), [
            'godown_id' => $godown->id,
            'client_ledger_id' => $client->id,
            'invoice_date' => $outside,
            'paid_amount' => 0,
            'payment_mode' => 'cash',
            'items' => [[
                'product_id' => $product->id,
                'batch_id' => null,
                'qty' => 1,
                'rate' => 10,
                'discount_pct' => 0,
            ]],
        ])->assertSessionHasErrors('invoice_date');
    }

    public function test_default_voucher_date_clamped_to_active_fy(): void
    {
        $fy = FinancialYear::current();
        $svc = app(FinancialYearService::class);

        $inside = $svc->defaultVoucherDate($fy->start_date->copy()->addMonth());
        $this->assertTrue($fy->contains($inside));

        $before = $svc->defaultVoucherDate($fy->start_date->copy()->subMonth());
        $this->assertSame($fy->start_date->toDateString(), $before);
    }
}
