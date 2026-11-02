<?php

namespace Tests\Feature;

use App\Models\Godown;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use App\Support\WorkContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardAlertsTest extends TestCase
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

    public function test_dashboard_shell_loads_without_heavy_counts(): void
    {
        $godown = Godown::first();

        $html = $this->actingAs($this->owner())
            ->withSession(['godown_id' => $godown->id, 'work_scope' => WorkContext::SCOPE_SHOP])
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(__('Shop pulse'), $html);
        $this->assertStringContainsString(__('Low stock'), $html);
        $this->assertStringContainsString(__('Expiry alerts'), $html);
        $this->assertStringContainsString('dashboard/pulse', $html);
        $this->assertStringContainsString('dashboard/figures', $html);
        $this->assertStringContainsString('dashboard/recent', $html);
        $this->assertStringContainsString('dashboard/alerts/low-stock', $html);
        $this->assertStringContainsString('dashboardBoard', $html);
        $this->assertStringContainsString('dash-skel', $html);
    }

    public function test_pulse_and_figures_json_endpoints(): void
    {
        $godown = Godown::first();
        $product = Product::first();
        $product->update(['reorder_level' => 100, 'is_active' => true, 'name' => 'Dash Count Product', 'batch_required' => true]);
        StockMovement::where('product_id', $product->id)->delete();
        ProductBatch::where('product_id', $product->id)->delete();
        DB::transaction(fn () => app(StockService::class)->receive([
            'product_id' => $product->id,
            'godown_id' => $godown->id,
            'batch_no' => 'LOW-1',
            'qty' => 1,
            'rate' => 10,
            'mrp' => 20,
            'expiry_date' => now()->addDays(10)->toDateString(),
            'type' => 'opening',
            'date' => now()->toDateString(),
        ]));

        $pulse = $this->actingAs($this->owner())
            ->withSession(['godown_id' => $godown->id, 'work_scope' => WorkContext::SCOPE_SHOP])
            ->getJson(route('dashboard.pulse'))
            ->assertOk()
            ->assertJsonStructure([
                'low_count', 'expiry_count', 'recv_count', 'pay_count',
                'receivable_fmt', 'payable_fmt', 'open_balance_fmt', 'low_hint', 'expiry_hint',
            ])
            ->json();

        $this->assertGreaterThanOrEqual(1, (int) $pulse['low_count']);
        $this->assertGreaterThanOrEqual(1, (int) $pulse['expiry_count']);

        $this->actingAs($this->owner())
            ->withSession(['godown_id' => $godown->id, 'work_scope' => WorkContext::SCOPE_SHOP])
            ->getJson(route('dashboard.figures'))
            ->assertOk()
            ->assertJsonStructure([
                'today_sales_fmt', 'today_count', 'month_sales_fmt',
                'month_purchases_fmt', 'stock_value_fmt',
            ]);

        $this->actingAs($this->owner())
            ->withSession(['godown_id' => $godown->id, 'work_scope' => WorkContext::SCOPE_SHOP])
            ->get(route('dashboard.recent'))
            ->assertOk();
    }

    public function test_alert_drawers_return_details_on_demand(): void
    {
        $godown = Godown::first();
        $product = Product::first();
        $product->update(['reorder_level' => 50, 'is_active' => true, 'name' => 'Alert Seed Pack', 'batch_required' => true]);
        StockMovement::where('product_id', $product->id)->delete();
        ProductBatch::where('product_id', $product->id)->delete();
        DB::transaction(fn () => app(StockService::class)->receive([
            'product_id' => $product->id,
            'godown_id' => $godown->id,
            'batch_no' => 'EXP-DRAW',
            'qty' => 2,
            'rate' => 10,
            'mrp' => 20,
            'expiry_date' => now()->addDays(5)->toDateString(),
            'type' => 'opening',
            'date' => now()->toDateString(),
        ]));

        $this->actingAs($this->owner())
            ->withSession(['godown_id' => $godown->id, 'work_scope' => WorkContext::SCOPE_SHOP])
            ->get(route('dashboard.alerts.low-stock'))
            ->assertOk()
            ->assertSee('Alert Seed Pack');

        $this->actingAs($this->owner())
            ->withSession(['godown_id' => $godown->id, 'work_scope' => WorkContext::SCOPE_SHOP])
            ->get(route('dashboard.alerts.expiry'))
            ->assertOk()
            ->assertSee('EXP-DRAW')
            ->assertSee('Alert Seed Pack');

        $this->actingAs($this->owner())
            ->get(route('dashboard.alerts.outstanding', ['side' => 'receivable']))
            ->assertOk();
    }

    public function test_work_scope_shop_and_godown_can_be_switched(): void
    {
        $godown = Godown::first();

        $this->actingAs($this->owner())
            ->from(route('dashboard'))
            ->post(route('godown.switch'), ['work_scope' => 'shop'])
            ->assertRedirect();

        $this->assertSame(WorkContext::SCOPE_SHOP, session('work_scope'));

        $this->actingAs($this->owner())
            ->from(route('dashboard'))
            ->post(route('godown.switch'), ['work_scope' => 'godown', 'godown_id' => $godown->id])
            ->assertRedirect();

        $this->assertSame(WorkContext::SCOPE_GODOWN, session('work_scope'));
        $this->assertEquals($godown->id, (int) session('godown_id'));
    }
}
