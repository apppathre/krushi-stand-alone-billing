<?php

namespace Tests\Feature;

use App\Models\DocumentSeries;
use App\Models\FinancialYear;
use App\Models\User;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentNumberTest extends TestCase
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

    public function test_peek_returns_preview_without_incrementing(): void
    {
        $this->actingAs($this->owner());
        $fy = FinancialYear::current();
        $series = DocumentSeries::where('financial_year_id', $fy->id)
            ->where('doc_type', 'sale')
            ->where('is_default', true)
            ->firstOrFail();
        $before = (int) $series->next_number;

        $res = $this->getJson(route('document-numbers.peek', [
            'type' => 'sale',
            'date' => $fy->start_date->toDateString(),
            'format_id' => $series->id,
        ]));

        $res->assertOk()
            ->assertJsonPath('format_id', $series->id)
            ->assertJsonStructure(['number', 'format_id', 'format_name', 'formats']);

        $this->assertStringContainsString($series->prefix, $res->json('number'));
        $this->assertSame($before, (int) $series->fresh()->next_number);
    }

    public function test_next_uses_selected_series_and_increments_only_that_row(): void
    {
        $fy = FinancialYear::current();
        $default = DocumentSeries::where('financial_year_id', $fy->id)
            ->where('doc_type', 'sale')
            ->where('is_default', true)
            ->firstOrFail();

        $alt = DocumentSeries::create([
            'doc_type' => 'sale',
            'financial_year_id' => $fy->id,
            'name' => 'Retail counter',
            'prefix' => 'RET',
            'next_number' => 7,
            'padding' => 4,
            'is_default' => false,
            'is_active' => true,
        ]);

        $gen = app(InvoiceNumberGenerator::class);
        $no = $gen->next('sale', $fy->start_date, $alt->id);

        $this->assertSame('RET/'.$fy->name.'/0007', $no);
        $this->assertSame(8, (int) $alt->fresh()->next_number);
        $this->assertSame((int) $default->next_number, (int) $default->fresh()->next_number);
    }

    public function test_sales_and_purchases_pages_include_series_picker(): void
    {
        $this->actingAs($this->owner());

        $this->get(route('sales.index'))
            ->assertOk()
            ->assertSee('document_series_id', false)
            ->assertSee('data-voucher-number-root', false);

        $this->get(route('purchases.index'))
            ->assertOk()
            ->assertSee('document_series_id', false)
            ->assertSee('data-voucher-number-root', false);
    }
}
