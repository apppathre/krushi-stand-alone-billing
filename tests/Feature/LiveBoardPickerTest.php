<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveBoardPickerTest extends TestCase
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

    public function test_live_board_drawer_lists_active_products_with_packing(): void
    {
        $product = Product::query()->where('is_active', true)->firstOrFail();
        $product->update([
            'packing' => '1 kg',
            'show_on_live_stock' => false,
        ]);

        $html = $this->actingAs($this->owner())
            ->get(route('products.live-board'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('liveBoardPicker(', $html);
        $this->assertTrue(
            str_contains($html, '"packing":"1 kg"') || str_contains($html, '\u0022packing\u0022:\u00221 kg\u0022'),
            'Drawer payload should include packing for the product'
        );
        $this->assertStringContainsString((string) $product->id, $html);
        $this->assertStringContainsString('show_ids[]', $html);
    }

    public function test_live_board_sync_sets_only_selected_active_products(): void
    {
        $products = Product::query()->where('is_active', true)->orderBy('id')->take(2)->get();
        $this->assertGreaterThanOrEqual(2, $products->count());

        $keep = $products[0];
        $drop = $products[1];
        Product::query()->where('is_active', true)->update(['show_on_live_stock' => true]);

        $this->actingAs($this->owner())
            ->postJson(route('products.live-board.sync'), [
                'show_ids' => [$keep->id],
                'sync_all' => '1',
            ])
            ->assertOk()
            ->assertJsonFragment(['message' => __('Live screen items saved. :n item(s) will show on the board.', ['n' => 1])]);

        $this->assertTrue($keep->fresh()->show_on_live_stock);
        $this->assertFalse($drop->fresh()->show_on_live_stock);
    }
}
