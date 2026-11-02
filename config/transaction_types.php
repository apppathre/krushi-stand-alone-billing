<?php

/**
 * Transaction Master types for Krushi billing.
 * Codes are stable. Seed creates one default format per type for each financial year.
 * Staff may add more formats of the same type (multi-series).
 */
return [

    'groups' => [
        'purchase' => 'Purchase',
        'sales' => 'Sales',
        'accounts' => 'Vouchers & notes',
        'stock' => 'Stock journals',
    ],

    /*
     * Every document that can take a number series.
     * posts_stock / posts_money describe future books behaviour (same idea as TMS).
     */
    'types' => [
        'purchase_order' => ['label' => 'Purchase Order', 'group' => 'purchase', 'prefix' => 'PO', 'sort' => 5, 'kind' => 'order', 'posts_stock' => false, 'posts_money' => false],
        'purchase' => ['label' => 'Purchase', 'group' => 'purchase', 'prefix' => 'PUR', 'sort' => 10, 'kind' => 'voucher', 'posts_stock' => true, 'posts_money' => true],
        'purchase_return' => ['label' => 'Purchase Return', 'group' => 'purchase', 'prefix' => 'PRN', 'sort' => 20, 'kind' => 'voucher', 'posts_stock' => true, 'posts_money' => true],
        'payment_voucher' => ['label' => 'Payment Voucher', 'group' => 'purchase', 'prefix' => 'PV', 'sort' => 30, 'kind' => 'voucher', 'posts_stock' => false, 'posts_money' => true],

        'sale' => ['label' => 'Sale / Invoice', 'group' => 'sales', 'prefix' => 'INV', 'sort' => 40, 'kind' => 'voucher', 'posts_stock' => true, 'posts_money' => true],
        // Credit note = sale return (stock + money). Single series code used by SaleService.
        'credit_note' => ['label' => 'Credit Note / Sale Return', 'group' => 'sales', 'prefix' => 'CN', 'sort' => 55, 'kind' => 'voucher', 'posts_stock' => true, 'posts_money' => true],
        'receipt_voucher' => ['label' => 'Receipt Voucher', 'group' => 'sales', 'prefix' => 'RV', 'sort' => 60, 'kind' => 'voucher', 'posts_stock' => false, 'posts_money' => true],

        'journal_voucher' => ['label' => 'Journal Voucher', 'group' => 'accounts', 'prefix' => 'JV', 'sort' => 70, 'kind' => 'voucher', 'posts_stock' => false, 'posts_money' => true],
        'contra_voucher' => ['label' => 'Contra Voucher', 'group' => 'accounts', 'prefix' => 'CV', 'sort' => 80, 'kind' => 'voucher', 'posts_stock' => false, 'posts_money' => true],
        // Debit note against purchase is numbered as purchase_return (PRN).

        'opening_stock' => ['label' => 'Opening Stock', 'group' => 'stock', 'prefix' => 'OS', 'sort' => 100, 'kind' => 'voucher', 'posts_stock' => true, 'posts_money' => false],
        'stock_journal' => ['label' => 'Stock Journal', 'group' => 'stock', 'prefix' => 'SJ', 'sort' => 110, 'kind' => 'voucher', 'posts_stock' => true, 'posts_money' => false],
        'physical_stock' => ['label' => 'Physical Stock', 'group' => 'stock', 'prefix' => 'PHY', 'sort' => 120, 'kind' => 'voucher', 'posts_stock' => true, 'posts_money' => false],
        'rejection' => ['label' => 'Stock Rejection', 'group' => 'stock', 'prefix' => 'REJ', 'sort' => 130, 'kind' => 'voucher', 'posts_stock' => true, 'posts_money' => false],
        'stock_issue' => ['label' => 'Stock Issue', 'group' => 'stock', 'prefix' => 'SI', 'sort' => 140, 'kind' => 'voucher', 'posts_stock' => true, 'posts_money' => false],
        'stock_return' => ['label' => 'Stock Return', 'group' => 'stock', 'prefix' => 'SRT', 'sort' => 150, 'kind' => 'voucher', 'posts_stock' => true, 'posts_money' => false],
        'stock_transfer' => ['label' => 'Stock Transfer', 'group' => 'stock', 'prefix' => 'ST', 'sort' => 160, 'kind' => 'voucher', 'posts_stock' => true, 'posts_money' => false],
    ],
];
