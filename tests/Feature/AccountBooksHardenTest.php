<?php

namespace Tests\Feature;

use App\Models\AccountVoucher;
use App\Models\Godown;
use App\Models\Ledger;
use App\Models\LedgerEntry;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\StockQty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Money path harden: account vouchers Dr=Cr, cancel cleans entries, trial balance after trade. */
class AccountBooksHardenTest extends TestCase
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

    private function assertVoucherBalanced(string $type, int $id): void
    {
        $row = LedgerEntry::query()
            ->where('voucher_type', $type)
            ->where('voucher_id', $id)
            ->selectRaw('COALESCE(SUM(debit),0) as dr, COALESCE(SUM(credit),0) as cr')
            ->first();
        $this->assertEquals(
            round((float) $row->dr, 2),
            round((float) $row->cr, 2),
            "Voucher {$type}#{$id} must balance Dr=Cr"
        );
        $this->assertGreaterThan(0.004, (float) $row->dr);
    }

    public function test_account_vouchers_balance_cancel_clears_and_no_stock(): void
    {
        $this->actingAs($this->owner());
        $cash = Ledger::query()->paymentInstruments()->where('type', 'cash')->firstOrFail();
        $bank = Ledger::query()->paymentInstruments()->where('type', 'bank')->firstOrFail();
        $expense = Ledger::books()->where('type', 'expense')->firstOrFail();
        $movesBefore = StockMovement::count();

        $this->post(route('account-vouchers.journal.store'), [
            'voucher_date' => now()->toDateString(),
            'amount' => 40,
            'debit_ledger_id' => $expense->id,
            'credit_ledger_id' => $cash->id,
            'narration' => 'Harden JV',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->post(route('account-vouchers.contra.store'), [
            'voucher_date' => now()->toDateString(),
            'amount' => 15,
            'from_ledger_id' => $cash->id,
            'to_ledger_id' => $bank->id,
            'narration' => 'Harden CV',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->post(route('account-vouchers.payment.store'), [
            'voucher_date' => now()->toDateString(),
            'amount' => 20,
            'account_ledger_id' => $expense->id,
            'cash_ledger_id' => $cash->id,
            'narration' => 'Harden PV',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->post(route('account-vouchers.receipt.store'), [
            'voucher_date' => now()->toDateString(),
            'amount' => 12,
            'account_ledger_id' => $expense->id,
            'cash_ledger_id' => $bank->id,
            'narration' => 'Harden RV',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame($movesBefore, StockMovement::count(), 'Account vouchers must not post stock');

        foreach (AccountVoucher::query()->latest('id')->limit(4)->get() as $v) {
            $this->assertVoucherBalanced($v->voucher_type, $v->id);
        }

        $jv = AccountVoucher::where('voucher_type', 'journal_voucher')->where('narration', 'Harden JV')->firstOrFail();
        $this->delete(route('account-vouchers.cancel', $jv))->assertRedirect();
        $this->assertSame(0, LedgerEntry::where('voucher_type', 'journal_voucher')->where('voucher_id', $jv->id)->count());
        $this->assertTrue($jv->fresh()->isCancelled());
    }

    public function test_sale_purchase_receipt_trial_balance_and_cancel_cleans_money(): void
    {
        $this->actingAs($this->owner());
        $godown = Godown::firstOrFail();
        $supplier = Ledger::where('type', 'supplier')->firstOrFail();
        $walkInId = (int) (\App\Models\AccountMap::current()->walk_in_ledger_id ?: 0);
        $client = Ledger::where('type', 'client')->when($walkInId, fn ($q) => $q->where('id', '!=', $walkInId))->firstOrFail();
        $product = Product::where('batch_required', true)->firstOrFail();
        $cashId = Ledger::query()->paymentInstruments()->where('type', 'cash')->value('id');

        $this->post(route('purchases.store'), [
            'godown_id' => $godown->id,
            'supplier_ledger_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'settlement_type' => 'credit',
            'items' => [[
                'product_id' => $product->id,
                'batch_no' => 'TB1',
                'expiry_date' => now()->addYear()->toDateString(),
                'qty' => 8,
                'rate' => 100,
                'discount_pct' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        $purchase = Purchase::latest('id')->firstOrFail();
        $this->assertVoucherBalanced('purchase', $purchase->id);

        $batch = ProductBatch::where('batch_no', 'TB1')->firstOrFail();
        $this->post(route('sales.store'), [
            'godown_id' => $godown->id,
            'client_ledger_id' => $client->id,
            'invoice_date' => now()->toDateString(),
            'settlement_type' => 'cash',
            'paid_amount' => 200,
            'payment_mode' => 'cash',
            'payment_ledger_id' => $cashId,
            'items' => [[
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'qty' => 2,
                'rate' => 150,
                'discount_pct' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        $sale = Sale::latest('id')->firstOrFail();
        $this->assertVoucherBalanced('sale', $sale->id);
        $receipt = $sale->payments()->firstOrFail();
        $this->assertVoucherBalanced('sale_receipt', $receipt->id);

        $totals = LedgerEntry::query()
            ->selectRaw('COALESCE(SUM(debit),0) as dr, COALESCE(SUM(credit),0) as cr')
            ->first();
        $this->assertEquals(round((float) $totals->dr, 2), round((float) $totals->cr, 2), 'Period Dr must equal Cr');

        $this->assertEquals(6.0, StockQty::freshBatchOnHand($batch->id));

        $this->delete(route('sales.cancel', $sale))->assertRedirect();
        $this->assertSame(0, LedgerEntry::where('voucher_type', 'sale')->where('voucher_id', $sale->id)->count());
        $this->assertSame(0, LedgerEntry::where('voucher_type', 'sale_receipt')->where('voucher_id', $receipt->id)->count());
        $this->assertEquals(8.0, StockQty::freshBatchOnHand($batch->id));

        $after = LedgerEntry::query()
            ->selectRaw('COALESCE(SUM(debit),0) as dr, COALESCE(SUM(credit),0) as cr')
            ->first();
        $this->assertEquals(round((float) $after->dr, 2), round((float) $after->cr, 2));
    }

    public function test_credit_note_posts_balanced_money_and_restores_stock(): void
    {
        $this->actingAs($this->owner());
        $godown = Godown::firstOrFail();
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
                'batch_no' => 'CN1',
                'expiry_date' => now()->addYear()->toDateString(),
                'qty' => 6,
                'rate' => 60,
                'discount_pct' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        $batch = ProductBatch::where('batch_no', 'CN1')->firstOrFail();
        $this->post(route('sales.store'), [
            'godown_id' => $godown->id,
            'client_ledger_id' => $client->id,
            'invoice_date' => now()->toDateString(),
            'settlement_type' => 'credit',
            'items' => [[
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'qty' => 4,
                'rate' => 90,
                'discount_pct' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        $sale = Sale::latest('id')->firstOrFail();
        $itemId = $sale->items()->first()->id;

        $this->post(route('sale-returns.store'), [
            'sale_id' => $sale->id,
            'return_date' => now()->toDateString(),
            'reason' => 'Harden CN',
            'qty' => [$itemId => 2],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $cn = $sale->returns()->latest('id')->firstOrFail();
        $this->assertVoucherBalanced('sale_return', $cn->id);
        $this->assertEquals(4.0, StockQty::freshBatchOnHand($batch->id)); // 6 - 4 + 2
        $this->assertEquals(
            StockQty::freshBatchOnHand($batch->id),
            (float) $batch->fresh()->qty_on_hand
        );
    }
}
