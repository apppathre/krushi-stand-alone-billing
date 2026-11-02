<?php

namespace App\Support;

/**
 * Shared include/exclude catalogue for Tally XML + Excel export UI.
 */
class ExportCatalog
{
    /** @return array<string, array{label:string,hint:string,types:list<string>,default:bool}> */
    public static function includes(): array
    {
        return [
            'ledgers' => [
                'label' => 'Ledgers / parties',
                'hint' => 'All active account & party ledgers (needed before Tally vouchers).',
                'types' => [],
                'default' => true,
            ],
            'sales' => [
                'label' => 'Sales invoices',
                'hint' => 'Posted sale bills and GST money entries.',
                'types' => ['sale'],
                'default' => true,
            ],
            'purchases' => [
                'label' => 'Purchase bills',
                'hint' => 'Posted purchases and input tax entries.',
                'types' => ['purchase'],
                'default' => true,
            ],
            'bill_receipts' => [
                'label' => 'Bill receipts',
                'hint' => 'Customer receipts against sale invoices.',
                'types' => ['sale_receipt'],
                'default' => true,
            ],
            'bill_payments' => [
                'label' => 'Bill payments',
                'hint' => 'Supplier payments against purchase bills.',
                'types' => ['purchase_payment'],
                'default' => true,
            ],
            'returns' => [
                'label' => 'Sale / purchase returns',
                'hint' => 'Credit notes and purchase returns.',
                'types' => ['sale_return', 'purchase_return'],
                'default' => true,
            ],
            'account_vouchers' => [
                'label' => 'Account vouchers',
                'hint' => 'Payment / receipt / journal / contra (rent, salary, cash–bank).',
                'types' => ['payment_voucher', 'receipt_voucher', 'journal_voucher', 'contra_voucher'],
                'default' => true,
            ],
            'commission' => [
                'label' => 'Sales commission',
                'hint' => 'Commission expense / payable journals from sales.',
                'types' => ['sale_commission'],
                'default' => false,
            ],
        ];
    }

    /** @return list<array{label:string,reason:string}> */
    public static function excluded(): array
    {
        return [
            ['label' => 'Stock journals / opening / put-away', 'reason' => 'Inventory only — no Tally stock item export yet.'],
            ['label' => 'Product / HSN / GST slab masters', 'reason' => 'Item catalogue stays in Krushi; Excel can list sales rates separately later.'],
            ['label' => 'Users, roles, menus, settings', 'reason' => 'App access — not accounting data.'],
            ['label' => 'Invoice PDF layouts / terms HTML', 'reason' => 'Print design, not books.'],
            ['label' => 'Unposted drafts', 'reason' => 'Only posted ledger entries are exported.'],
        ];
    }

    /**
     * @param  list<string>  $includeKeys
     * @return list<string> voucher_type values (empty = none)
     */
    public static function voucherTypesFor(array $includeKeys): array
    {
        $types = [];
        foreach (self::includes() as $key => $meta) {
            if ($key === 'ledgers') {
                continue;
            }
            if (in_array($key, $includeKeys, true)) {
                $types = array_merge($types, $meta['types']);
            }
        }

        return array_values(array_unique($types));
    }

    /** @return list<string> */
    public static function defaultIncludeKeys(): array
    {
        return collect(self::includes())->filter(fn ($m) => $m['default'])->keys()->all();
    }

    /** @return array<string, array{label:string,hint:string,default:bool}> */
    public static function importIncludes(): array
    {
        return [
            'ledgers' => [
                'label' => 'Ledgers / parties',
                'hint' => 'Tally LEDGER masters → Krushi ledgers (group maps to client/supplier/cash/bank…).',
                'default' => true,
            ],
            'products' => [
                'label' => 'Stock items',
                'hint' => 'Tally STOCKITEM → products (name, HSN, rate, unit, category). No opening qty.',
                'default' => false,
            ],
            'vouchers' => [
                'label' => 'Vouchers / transactions',
                'hint' => 'Tally VOUCHER accounting lines → account vouchers (payment/receipt/journal/contra).',
                'default' => true,
            ],
        ];
    }

    /** @return list<array{label:string,reason:string}> */
    public static function importExcluded(): array
    {
        return [
            ['label' => 'Sale / purchase bill inventory lines', 'reason' => 'Item qty from Tally vouchers is not posted to stock — accounting lines only.'],
            ['label' => 'Godown / batch / rack masters', 'reason' => 'Keep place & batch setup in Krushi.'],
            ['label' => 'Users, roles, print layouts', 'reason' => 'App settings — not Tally data.'],
            ['label' => 'Duplicate vouchers', 'reason' => 'Same Tally voucher number + date is skipped (tag in narration).'],
        ];
    }

    /** @return list<string> */
    public static function defaultImportKeys(): array
    {
        return collect(self::importIncludes())->filter(fn ($m) => $m['default'])->keys()->all();
    }
}
