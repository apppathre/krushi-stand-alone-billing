<?php

namespace Tests\Feature;

use App\Models\Godown;
use App\Models\Ledger;
use App\Models\LedgerEntry;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkSettleTest extends TestCase
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

    public function test_bulk_receipt_pages_render(): void
    {
        $this->actingAs($this->owner());
        $this->get(route('bulk-receipts.create'))->assertOk();
        $this->get(route('bulk-payments.create'))->assertOk();
    }

    public function test_bulk_receipt_with_party_id_stays_stable_and_shows_dues(): void
    {
        $this->actingAs($this->owner());
        $godown = Godown::first();
        $supplier = Ledger::where('type', 'supplier')->firstOrFail();
        $walkInId = (int) (\App\Models\AccountMap::current()->walk_in_ledger_id ?: 0);
        $client = Ledger::where('type', 'client')->when($walkInId, fn ($q) => $q->where('id', '!=', $walkInId))->firstOrFail();
        $product = Product::where('batch_required', true)->firstOrFail();

        $this->post(route('purchases.store'), [
            'godown_id' => $godown->id,
            'supplier_ledger_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'settlement_type' => 'credit',
            'items' => [[
                'product_id' => $product->id,
                'batch_no' => 'BR-LOOP',
                'expiry_date' => now()->addYear()->toDateString(),
                'qty' => 5,
                'rate' => 40,
                'discount_pct' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        $batchId = ProductBatch::where('batch_no', 'BR-LOOP')->value('id');
        $this->post(route('sales.store'), [
            'godown_id' => $godown->id,
            'client_ledger_id' => $client->id,
            'invoice_date' => now()->toDateString(),
            'settlement_type' => 'credit',
            'items' => [[
                'product_id' => $product->id,
                'batch_id' => $batchId,
                'qty' => 1,
                'rate' => 90,
                'discount_pct' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        $sale = Sale::latest('id')->firstOrFail();

        // Loading with party_id must 200 (not redirect loop) and list the open invoice.
        // Party filter uses data-filter-submit (Select2 user pick) — not inline onchange
        // which used to refresh-loop when Select2 initialized with a selected party.
        $html = $this->get(route('bulk-receipts.create', ['party_id' => $client->id]))
            ->assertOk()
            ->assertSee($sale->invoice_no, false)
            ->getContent();
        $this->assertStringContainsString('data-filter-submit="1"', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/id="f-party_id"[^>]*onchange="this\.form\.submit\(\)"/',
            $html
        );

        $this->get(route('bulk-payments.create', ['party_id' => $supplier->id]))
            ->assertOk()
            ->assertSee('data-filter-submit="1"', false);
    }

    public function test_bulk_receipt_settles_full_then_partial_and_updates_ledger(): void
    {
        $this->actingAs($this->owner());
        $godown = Godown::first();
        $supplier = Ledger::where('type', 'supplier')->firstOrFail();
        $walkInId = (int) (\App\Models\AccountMap::current()->walk_in_ledger_id ?: 0);
        $client = Ledger::where('type', 'client')->when($walkInId, fn ($q) => $q->where('id', '!=', $walkInId))->firstOrFail();
        $product = Product::where('batch_required', true)->firstOrFail();

        $this->post(route('purchases.store'), [
            'godown_id' => $godown->id,
            'supplier_ledger_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'supplier_invoice_no' => 'BLK-PUR',
            'paid_amount' => 0,
            'items' => [[
                'product_id' => $product->id,
                'batch_no' => 'BLK-B1',
                'expiry_date' => now()->addYear()->toDateString(),
                'qty' => 20,
                'rate' => 50,
                'discount_pct' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        $batchId = ProductBatch::where('batch_no', 'BLK-B1')->value('id');

        foreach ([now()->subDays(2)->toDateString(), now()->subDay()->toDateString()] as $i => $date) {
            $this->post(route('sales.store'), [
                'godown_id' => $godown->id,
                'client_ledger_id' => $client->id,
                'invoice_date' => $date,
                'paid_amount' => 0,
                'items' => [[
                    'product_id' => $product->id,
                    'batch_id' => $batchId,
                    'qty' => 1,
                    'rate' => 100,
                    'discount_pct' => 0,
                ]],
            ])->assertSessionHasNoErrors();
        }

        $sales = Sale::where('client_ledger_id', $client->id)
            ->where('status', 'active')
            ->openDue()
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->take(2)
            ->get();
        $this->assertCount(2, $sales);

        $a = $sales[0];
        $b = $sales[1];
        $dueA = (float) $a->balance;
        $dueB = (float) $b->balance;
        $lump = round($dueA + ($dueB / 2), 2);

        $beforeParty = (float) LedgerEntry::where('ledger_id', $client->id)
            ->selectRaw('COALESCE(SUM(debit-credit),0) as b')->value('b');

        $this->post(route('bulk-receipts.store'), [
            'party_id' => $client->id,
            'payment_date' => now()->toDateString(),
            'mode' => 'cash',
            'amount' => $lump,
            'reference' => 'BLK-TEST-1',
            'bill_ids' => [$a->id, $b->id],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $a = $a->fresh();
        $b = $b->fresh();
        $this->assertSame(0.0, (float) $a->balance);
        $this->assertEqualsWithDelta($dueB / 2, (float) $b->balance, 0.05);
        $this->assertSame('BLK-TEST-1', $a->payments()->latest('id')->first()->reference);
        $this->assertSame('BLK-TEST-1', $b->payments()->latest('id')->first()->reference);

        $afterParty = (float) LedgerEntry::where('ledger_id', $client->id)
            ->selectRaw('COALESCE(SUM(debit-credit),0) as b')->value('b');
        $this->assertEqualsWithDelta($beforeParty - $lump, $afterParty, 0.05);

        $this->get(route('reports.outstanding'))->assertOk();
        $this->get(route('reports.party-ledger', ['ledger_id' => $client->id]))
            ->assertOk()
            ->assertSee('BLK-TEST-1', false);
    }
}
