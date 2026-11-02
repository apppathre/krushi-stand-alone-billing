<?php

namespace App\Services;

use App\Models\AccountVoucher;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;

/**
 * Import TallyPrime / Tally.ERP 9 XML (ENVELOPE / TALLYMESSAGE) into Krushi.
 * Masters: LEDGER (+ STOCKITEM as products). Transactions: VOUCHER → account vouchers.
 */
class TallyImportService
{
    /** Tally parent group (lowercase) → [family, type] */
    private const GROUP_TO_TYPE = [
        'sundry debtors' => ['party', 'client'],
        'sundry creditors' => ['party', 'supplier'],
        'cash-in-hand' => ['account', 'cash'],
        'bank accounts' => ['account', 'bank'],
        'bank od a/c' => ['account', 'bank'],
        'sales accounts' => ['account', 'sales'],
        'purchase accounts' => ['account', 'purchase'],
        'direct expenses' => ['account', 'expense'],
        'indirect expenses' => ['account', 'expense'],
        'direct incomes' => ['account', 'income'],
        'indirect incomes' => ['account', 'income'],
        'duties & taxes' => ['account', 'tax'],
        'duties and taxes' => ['account', 'tax'],
        'capital account' => ['account', 'capital'],
        'fixed assets' => ['account', 'other'],
        'current assets' => ['account', 'other'],
        'current liabilities' => ['account', 'other'],
        'suspense a/c' => ['account', 'other'],
    ];

    /** Tally VCHTYPE → our account voucher type */
    private const VCH_TO_TYPE = [
        'sales' => 'journal_voucher',
        'purchase' => 'journal_voucher',
        'receipt' => 'receipt_voucher',
        'payment' => 'payment_voucher',
        'journal' => 'journal_voucher',
        'contra' => 'contra_voucher',
        'credit note' => 'journal_voucher',
        'debit note' => 'journal_voucher',
    ];

    public function __construct(
        private AccountVoucherService $vouchers,
    ) {}

    /**
     * @param  list<string>  $include  ledgers|products|vouchers
     * @return array{ledgers_created:int,ledgers_updated:int,products_created:int,products_updated:int,vouchers_created:int,vouchers_skipped:int,errors:list<string>}
     */
    public function importXml(string $xml, array $include, ?int $userId = null): array
    {
        $include = array_values(array_unique($include));
        if ($include === []) {
            throw ValidationException::withMessages(['include' => __('Select at least one import section.')]);
        }

        $root = $this->parseXml($xml);
        $stats = [
            'ledgers_created' => 0,
            'ledgers_updated' => 0,
            'products_created' => 0,
            'products_updated' => 0,
            'vouchers_created' => 0,
            'vouchers_skipped' => 0,
            'errors' => [],
        ];

        DB::transaction(function () use ($root, $include, $userId, &$stats) {
            if (in_array('ledgers', $include, true)) {
                foreach ($this->findNodes($root, 'LEDGER') as $node) {
                    try {
                        $r = $this->importLedger($node);
                        $stats[$r === 'created' ? 'ledgers_created' : 'ledgers_updated']++;
                    } catch (\Throwable $e) {
                        $stats['errors'][] = __('Ledger').': '.$e->getMessage();
                    }
                }
            }

            if (in_array('products', $include, true)) {
                foreach ($this->findNodes($root, 'STOCKITEM') as $node) {
                    try {
                        $r = $this->importStockItem($node);
                        $stats[$r === 'created' ? 'products_created' : 'products_updated']++;
                    } catch (\Throwable $e) {
                        $stats['errors'][] = __('Product').': '.$e->getMessage();
                    }
                }
            }

            if (in_array('vouchers', $include, true)) {
                foreach ($this->findNodes($root, 'VOUCHER') as $node) {
                    try {
                        $r = $this->importVoucher($node, $userId);
                        if ($r === 'skipped') {
                            $stats['vouchers_skipped']++;
                        } else {
                            $stats['vouchers_created']++;
                        }
                    } catch (\Throwable $e) {
                        $stats['errors'][] = __('Voucher').': '.$e->getMessage();
                    }
                }
            }
        });

        return $stats;
    }

    private function parseXml(string $xml): SimpleXMLElement
    {
        $xml = trim($xml);
        if ($xml === '') {
            throw ValidationException::withMessages(['file' => __('XML file is empty.')]);
        }

        // Strip BOM / invalid control chars that break SimpleXML
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $xml) ?? $xml;
        $xml = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $xml) ?? $xml;

        libxml_use_internal_errors(true);
        $root = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
        if ($root === false) {
            $msg = collect(libxml_get_errors())->map(fn ($e) => trim($e->message))->filter()->take(3)->implode(' ');
            libxml_clear_errors();
            throw ValidationException::withMessages(['file' => __('Invalid Tally XML.').($msg ? ' '.$msg : '')]);
        }
        libxml_clear_errors();

        return $root;
    }

    /** @return list<SimpleXMLElement> */
    private function findNodes(SimpleXMLElement $root, string $tag): array
    {
        $out = [];
        $this->walk($root, $tag, $out);

        return $out;
    }

    /** @param  list<SimpleXMLElement>  $out */
    private function walk(SimpleXMLElement $node, string $tag, array &$out): void
    {
        $name = $node->getName();
        if (strcasecmp($name, $tag) === 0) {
            $out[] = $node;
        }
        foreach ($node->children() as $child) {
            $this->walk($child, $tag, $out);
        }
    }

    private function importLedger(SimpleXMLElement $node): string
    {
        $name = $this->attrOrChild($node, 'NAME') ?: (string) ($node->{'NAME.LIST'}->NAME ?? '');
        $name = trim(html_entity_decode($name));
        if ($name === '') {
            throw new \RuntimeException(__('Missing ledger name.'));
        }

        $parent = trim((string) ($node->PARENT ?? ''));
        [$family, $type] = $this->mapGroup($parent);

        $openingRaw = trim((string) ($node->OPENINGBALANCE ?? '0'));
        $openingRaw = str_replace([',', ' '], '', $openingRaw);
        $opening = (float) $openingRaw;
        $openingType = $opening < 0 ? 'cr' : 'dr';
        $opening = abs($opening);

        $gstin = trim((string) ($node->PARTYGSTIN ?? $node->LEDGERGSTIN ?? ''));
        $phone = trim((string) ($node->LEDGERPHONE ?? $node->PHONE ?? ''));
        $address = trim((string) ($node->ADDRESS ?? ($node->{'ADDRESS.LIST'}->ADDRESS ?? '')));

        $existing = Ledger::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        $payload = [
            'name' => $name,
            'family' => $family,
            'type' => $type,
            'opening_balance' => $opening,
            'opening_type' => $openingType,
            'gstin' => $gstin !== '' ? $gstin : ($existing->gstin ?? null),
            'phone' => $phone !== '' ? $phone : ($existing->phone ?? null),
            'address' => $address !== '' ? $address : ($existing->address ?? null),
            'is_active' => true,
        ];

        if ($existing) {
            if (! $existing->is_system) {
                $existing->update(collect($payload)->except(['name'])->all());
            }

            return 'updated';
        }

        Ledger::create($payload);

        return 'created';
    }

    private function importStockItem(SimpleXMLElement $node): string
    {
        $name = $this->attrOrChild($node, 'NAME') ?: (string) ($node->{'NAME.LIST'}->NAME ?? '');
        $name = trim(html_entity_decode($name));
        if ($name === '') {
            throw new \RuntimeException(__('Missing stock item name.'));
        }

        $parent = trim((string) ($node->PARENT ?? '')) ?: __('General');
        $unitName = trim((string) ($node->BASEUNITS ?? $node->DENOMINATORUNITS ?? 'Nos')) ?: 'Nos';
        $hsn = trim((string) ($node->HSNCODE ?? $node->{'GSTDETAILS.LIST'}->HSNCODE ?? ''));
        $gst = (float) str_replace(',', '', (string) ($node->GSTAPPLICABLETAXRATE ?? $node->{'GSTDETAILS.LIST'}->TAXABILITY ?? '0'));
        $rate = (float) str_replace(',', '', (string) ($node->{'RATEDETAILS.LIST'}->RATE ?? $node->RATE ?? '0'));
        // RATE often like "100.00/Nos"
        if (preg_match('/([\d.]+)/', (string) ($node->{'RATEDETAILS.LIST'}->RATE ?? ''), $m)) {
            $rate = (float) $m[1];
        }

        $category = Category::firstOrCreate(
            ['name' => $parent],
            ['name_mr' => null, 'is_active' => true]
        );
        $unit = Unit::query()
            ->where(function ($q) use ($unitName) {
                $q->whereRaw('LOWER(short_name) = ?', [mb_strtolower($unitName)])
                    ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($unitName)]);
            })
            ->first();
        if (! $unit) {
            $unit = Unit::create([
                'name' => $unitName,
                'short_name' => Str::limit($unitName, 10, ''),
                'is_active' => true,
            ]);
        }

        $existing = Product::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        $payload = [
            'name' => $name,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'hsn_code' => $hsn !== '' ? $hsn : ($existing->hsn_code ?? null),
            'gst_rate' => $gst > 0 ? $gst : (float) ($existing->gst_rate ?? 0),
            'sale_rate' => $rate > 0 ? $rate : (float) ($existing->sale_rate ?? 0),
            'purchase_rate' => $rate > 0 ? $rate : (float) ($existing->purchase_rate ?? 0),
            'mrp' => $rate > 0 ? $rate : (float) ($existing->mrp ?? 0),
            'is_active' => true,
            'batch_required' => $existing->batch_required ?? false,
        ];

        if ($existing) {
            $existing->update($payload);

            return 'updated';
        }

        $product = Product::create($payload);
        if (blank($product->code)) {
            $product->update(['code' => 'P'.str_pad((string) $product->id, 5, '0', STR_PAD_LEFT)]);
        }

        return 'created';
    }

    private function importVoucher(SimpleXMLElement $node, ?int $userId): string
    {
        $vchTypeName = trim((string) ($node->VOUCHERTYPENAME ?? $node['VCHTYPE'] ?? 'Journal'));
        $ourType = self::VCH_TO_TYPE[mb_strtolower($vchTypeName)] ?? 'journal_voucher';

        $dateRaw = trim((string) ($node->DATE ?? $node->EFFECTIVEDATE ?? ''));
        $date = $this->parseTallyDate($dateRaw);
        if (! $date) {
            throw new \RuntimeException(__('Voucher missing date (:type).', ['type' => $vchTypeName]));
        }

        $voucherNo = trim((string) ($node->VOUCHERNUMBER ?? $node->REFERENCE ?? ''));
        $narration = trim((string) ($node->NARRATION ?? ''));
        $tallyTag = $voucherNo !== '' ? '[Tally:'.$voucherNo.']' : '[Tally:'.$date.'-'.$vchTypeName.']';

        $exists = AccountVoucher::query()
            ->whereDate('voucher_date', $date)
            ->where('narration', 'like', '%'.$tallyTag.'%')
            ->where('status', 'active')
            ->exists();
        if ($exists) {
            return 'skipped';
        }

        $lines = $this->extractLedgerLines($node);
        if (count($lines) < 2) {
            throw new \RuntimeException(__('Voucher :no has fewer than 2 ledger lines.', ['no' => $voucherNo ?: $vchTypeName]));
        }

        $dr = round(array_sum(array_column($lines, 'debit')), 2);
        $cr = round(array_sum(array_column($lines, 'credit')), 2);
        if (abs($dr - $cr) > 0.05) {
            throw new \RuntimeException(__('Voucher :no unbalanced (Dr :dr / Cr :cr).', [
                'no' => $voucherNo ?: $vchTypeName,
                'dr' => $dr,
                'cr' => $cr,
            ]));
        }

        $resolved = [];
        foreach ($lines as $line) {
            $ledger = $this->findOrCreateLedgerByName($line['name']);
            $resolved[] = [
                'ledger_id' => $ledger->id,
                'debit' => $line['debit'],
                'credit' => $line['credit'],
                'narration' => $line['narration'] ?: $narration,
            ];
        }

        $fullNarration = trim(($narration !== '' ? $narration.' ' : '').$tallyTag);

        $this->vouchers->createMultiLine([
            'voucher_type' => $ourType,
            'voucher_date' => $date,
            'narration' => $fullNarration,
            'lines' => $resolved,
        ], $userId);

        return 'created';
    }

    /** @return list<array{name:string,debit:float,credit:float,narration:?string}> */
    private function extractLedgerLines(SimpleXMLElement $node): array
    {
        $lines = [];
        $entries = array_merge(
            $node->xpath('.//ALLLEDGERENTRIES.LIST') ?: [],
            $node->xpath('.//LEDGERENTRIES.LIST') ?: [],
        );

        foreach ($entries as $entry) {
            $name = trim((string) ($entry->LEDGERNAME ?? ''));
            if ($name === '') {
                continue;
            }
            $amountRaw = str_replace([',', ' '], '', trim((string) ($entry->AMOUNT ?? '0')));
            $amount = (float) $amountRaw;
            $abs = abs($amount);
            if ($abs < 0.005) {
                continue;
            }

            $deemed = strtolower(trim((string) ($entry->ISDEEMEDPOSITIVE ?? '')));
            if ($deemed === 'yes') {
                $debit = $abs;
                $credit = 0.0;
            } elseif ($deemed === 'no') {
                $debit = 0.0;
                $credit = $abs;
            } else {
                // Signed amount fallback: + debit, − credit
                $debit = $amount > 0 ? $abs : 0.0;
                $credit = $amount < 0 ? $abs : 0.0;
            }

            $lines[] = [
                'name' => $name,
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
                'narration' => trim((string) ($entry->NARRATION ?? '')) ?: null,
            ];
        }

        return $lines;
    }

    private function findOrCreateLedgerByName(string $name): Ledger
    {
        $name = trim($name);
        $ledger = Ledger::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if ($ledger) {
            return $ledger;
        }

        return Ledger::create([
            'name' => $name,
            'family' => 'account',
            'type' => 'other',
            'opening_balance' => 0,
            'opening_type' => 'dr',
            'is_active' => true,
        ]);
    }

    /** @return array{0:string,1:string} */
    private function mapGroup(string $parent): array
    {
        $key = mb_strtolower(trim($parent));
        if ($key !== '' && isset(self::GROUP_TO_TYPE[$key])) {
            return self::GROUP_TO_TYPE[$key];
        }
        foreach (self::GROUP_TO_TYPE as $group => $map) {
            if ($key !== '' && str_contains($key, $group)) {
                return $map;
            }
        }

        return ['account', 'other'];
    }

    private function parseTallyDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        // YYYYMMDD
        if (preg_match('/^\d{8}$/', $raw)) {
            return substr($raw, 0, 4).'-'.substr($raw, 4, 2).'-'.substr($raw, 6, 2);
        }
        // YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }
        // DD-MM-YYYY
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $raw, $m)) {
            return $m[3].'-'.$m[2].'-'.$m[1];
        }

        try {
            return \Illuminate\Support\Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function attrOrChild(SimpleXMLElement $node, string $attr): string
    {
        $attrs = $node->attributes();
        if ($attrs && isset($attrs[$attr])) {
            return trim((string) $attrs[$attr]);
        }

        return trim((string) ($node->{$attr} ?? ''));
    }

}
