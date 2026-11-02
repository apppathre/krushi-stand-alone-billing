<?php

namespace Tests\Feature;

use App\Models\Godown;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\User;
use App\Models\AccountVoucher;
use App\Services\TallyExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TallyExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! User::where('email', \Database\Seeders\UserSeeder::OWNER_EMAIL)->exists()) {
            $this->seed();
        }
    }

    public function test_tally_export_page_and_xml_download(): void
    {
        $owner = User::where('email', \Database\Seeders\UserSeeder::OWNER_EMAIL)->firstOrFail();
        $this->actingAs($owner);

        // Ensure AccessSeeder menus include tally export for owner
        $this->seed(\Database\Seeders\AccessSeeder::class);
        User::forgetMenuCache();

        $this->get(route('reports.tally-export'))->assertOk()->assertSee('Tally', false);

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
                'product_id' => $product->id, 'batch_no' => 'TX1',
                'expiry_date' => now()->addYear()->toDateString(),
                'qty' => 2, 'rate' => 100, 'discount_pct' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        $this->post(route('sales.store'), [
            'godown_id' => $godown->id,
            'client_ledger_id' => $client->id,
            'invoice_date' => now()->toDateString(),
            'paid_amount' => 0,
            'items' => [[
                'product_id' => $product->id,
                'batch_id' => \App\Models\ProductBatch::where('batch_no', 'TX1')->value('id'),
                'qty' => 1, 'rate' => 150, 'discount_pct' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $include = ['ledgers', 'sales', 'purchases', 'bill_receipts', 'bill_payments', 'returns', 'account_vouchers'];

        foreach (['prime', 'erp9'] as $edition) {
            foreach (['masters', 'vouchers', 'both'] as $scope) {
                $res = $this->get(route('reports.tally-export.download', [
                    'format' => 'xml',
                    'edition' => $edition,
                    'scope' => $scope,
                    'from' => $from,
                    'to' => $to,
                    'include' => $include,
                ]));
                $res->assertOk();
                $body = $res->getContent();
                $this->assertStringContainsString('<ENVELOPE>', $body);
                $this->assertStringContainsString('<TALLYREQUEST>Import Data</TALLYREQUEST>', $body);
                if ($scope !== 'vouchers') {
                    $this->assertStringContainsString('<LEDGER ', $body);
                }
                if ($scope !== 'masters') {
                    $this->assertStringContainsString('<VOUCHER ', $body);
                }
            }
        }

        $xls = $this->get(route('reports.tally-export.download', [
            'format' => 'xls',
            'from' => $from,
            'to' => $to,
            'include' => $include,
        ]));
        $xls->assertOk();
        $this->assertStringContainsString('Excel.Sheet', $xls->getContent());
        $this->assertStringContainsString('<Worksheet', $xls->getContent());

        $xml = app(TallyExportService::class)->exportXml('prime', 'both', $from, $to, null, $include);
        $this->assertTrue(simplexml_load_string($xml) !== false);
    }

    public function test_tally_xml_import_masters_and_voucher(): void
    {
        $owner = User::where('email', \Database\Seeders\UserSeeder::OWNER_EMAIL)->firstOrFail();
        $this->actingAs($owner);
        $this->seed(\Database\Seeders\AccessSeeder::class);
        User::forgetMenuCache();

        $this->get(route('reports.tally-export', ['tab' => 'import']))->assertOk()->assertSee('Import', false);

        $date = now()->format('Ymd');
        $sample = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<ENVELOPE>
  <BODY><IMPORTDATA><REQUESTDATA>
    <TALLYMESSAGE>
      <LEDGER NAME="Tally Import Party" ACTION="Create">
        <NAME.LIST><NAME>Tally Import Party</NAME></NAME.LIST>
        <PARENT>Sundry Debtors</PARENT>
        <OPENINGBALANCE>250.00</OPENINGBALANCE>
      </LEDGER>
    </TALLYMESSAGE>
    <TALLYMESSAGE>
      <LEDGER NAME="Tally Import Cash" ACTION="Create">
        <NAME.LIST><NAME>Tally Import Cash</NAME></NAME.LIST>
        <PARENT>Cash-in-Hand</PARENT>
        <OPENINGBALANCE>0</OPENINGBALANCE>
      </LEDGER>
    </TALLYMESSAGE>
    <TALLYMESSAGE>
      <VOUCHER VCHTYPE="Receipt" ACTION="Create">
        <DATE>{$date}</DATE>
        <VOUCHERTYPENAME>Receipt</VOUCHERTYPENAME>
        <VOUCHERNUMBER>TIMP-1</VOUCHERNUMBER>
        <NARRATION>Test import receipt</NARRATION>
        <ALLLEDGERENTRIES.LIST>
          <LEDGERNAME>Tally Import Cash</LEDGERNAME>
          <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>
          <AMOUNT>100.00</AMOUNT>
        </ALLLEDGERENTRIES.LIST>
        <ALLLEDGERENTRIES.LIST>
          <LEDGERNAME>Tally Import Party</LEDGERNAME>
          <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>
          <AMOUNT>-100.00</AMOUNT>
        </ALLLEDGERENTRIES.LIST>
      </VOUCHER>
    </TALLYMESSAGE>
  </REQUESTDATA></IMPORTDATA></BODY>
</ENVELOPE>
XML;

        $file = UploadedFile::fake()->createWithContent('tally-import.xml', $sample);
        $res = $this->post(route('reports.tally-export.import'), [
            'file' => $file,
            'include' => ['ledgers', 'vouchers'],
        ]);
        $res->assertRedirect();
        $res->assertSessionHas('tally_import_result');

        $this->assertDatabaseHas('ledgers', ['name' => 'Tally Import Party']);
        $this->assertTrue(
            AccountVoucher::query()->where('narration', 'like', '%[Tally:TIMP-1]%')->exists()
        );

        // Duplicate skip
        $file2 = UploadedFile::fake()->createWithContent('tally-import-2.xml', $sample);
        $this->post(route('reports.tally-export.import'), [
            'file' => $file2,
            'include' => ['vouchers'],
        ])->assertSessionHas('tally_import_result');
        $result = session('tally_import_result');
        $this->assertGreaterThanOrEqual(1, (int) ($result['vouchers_skipped'] ?? 0));
    }

    public function test_account_voucher_desks_and_new_reports_render(): void
    {
        $owner = User::where('email', \Database\Seeders\UserSeeder::OWNER_EMAIL)->firstOrFail();
        $this->actingAs($owner);
        $this->seed(\Database\Seeders\AccessSeeder::class);
        User::forgetMenuCache();

        foreach ([
            'account-vouchers.payment',
            'account-vouchers.receipt',
            'account-vouchers.journal',
            'account-vouchers.contra',
            'account-vouchers.register',
            'reports.cash-book',
            'reports.trial-balance',
            'reports.balance-sheet',
            'reports.tally-export',
        ] as $name) {
            $this->get(route($name))->assertOk();
        }

        // Post a simple journal + contra and ensure they save
        $cash = Ledger::query()->paymentInstruments()->where('type', 'cash')->first();
        $bank = Ledger::query()->paymentInstruments()->where('type', 'bank')->first();
        $expense = Ledger::books()->where('type', 'expense')->first();

        $this->post(route('account-vouchers.journal.store'), [
            'voucher_date' => now()->toDateString(),
            'amount' => 25,
            'debit_ledger_id' => $expense->id,
            'credit_ledger_id' => $cash->id,
            'narration' => 'Test journal',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->post(route('account-vouchers.contra.store'), [
            'voucher_date' => now()->toDateString(),
            'amount' => 10,
            'from_ledger_id' => $cash->id,
            'to_ledger_id' => $bank->id,
            'narration' => 'Cash to bank',
        ])->assertSessionHasNoErrors()->assertRedirect();
    }
}
