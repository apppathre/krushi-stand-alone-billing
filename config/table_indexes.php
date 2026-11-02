<?php

/**
 * Indexes for live install / Server maintenance → Run table indexes.
 * Prefer names already used in migrations when they exist.
 * Safe to run again — existing index names are skipped.
 */
return [
    // Net-new composites (not created by older migrations)
    [
        'table' => 'ledgers',
        'name' => 'ledgers_family_type_active_idx',
        'columns' => ['family', 'type', 'is_active'],
        'why' => 'Customer / supplier / book lists filter by family, type, and active.',
    ],
    [
        'table' => 'ledgers',
        'name' => 'ledgers_salesperson_idx',
        'columns' => ['salesperson_user_id'],
        'why' => 'Default salesperson lookups on parties.',
    ],
    [
        'table' => 'sales',
        'name' => 'sales_client_status_date_idx',
        'columns' => ['client_ledger_id', 'status', 'invoice_date'],
        'why' => 'Bulk receipt and outstanding dues by customer.',
    ],
    [
        'table' => 'sales',
        'name' => 'sales_godown_status_date_idx',
        'columns' => ['godown_id', 'status', 'invoice_date'],
        'why' => 'Sales desk and registers scoped by place.',
    ],
    [
        'table' => 'purchases',
        'name' => 'purchases_supplier_status_date_idx',
        'columns' => ['supplier_ledger_id', 'status', 'purchase_date'],
        'why' => 'Bulk payment and supplier dues.',
    ],
    [
        'table' => 'purchases',
        'name' => 'purchases_godown_status_date_idx',
        'columns' => ['godown_id', 'status', 'purchase_date'],
        'why' => 'Purchase lists scoped by place.',
    ],
    [
        'table' => 'sale_payments',
        'name' => 'sale_payments_sale_date_idx',
        'columns' => ['sale_id', 'payment_date'],
        'why' => 'Receipt history on an invoice.',
    ],
    [
        'table' => 'purchase_payments',
        'name' => 'purchase_payments_purchase_date_idx',
        'columns' => ['purchase_id', 'payment_date'],
        'why' => 'Payment history on a purchase bill.',
    ],
    [
        'table' => 'products',
        'name' => 'products_active_name_idx',
        'columns' => ['is_active', 'name'],
        'why' => 'Item pickers and catalogs.',
    ],
    [
        'table' => 'users',
        'name' => 'users_active_role_idx',
        'columns' => ['is_active', 'role_id'],
        'why' => 'Staff lists and salesperson pickers.',
    ],

    // Names matching migrations — ensure present after partial / manual installs
    [
        'table' => 'stock_movements',
        'name' => 'stock_movements_bucket_idx',
        'columns' => ['product_id', 'godown_id', 'batch_id'],
        'why' => 'On-hand qty = SUM by product + godown + batch.',
    ],
    [
        'table' => 'stock_movements',
        'name' => 'stock_movements_batch_date_idx',
        'columns' => ['batch_id', 'movement_date'],
        'why' => 'Batch movement history.',
    ],
    [
        'table' => 'document_series',
        'name' => 'document_series_fy_type_idx',
        'columns' => ['financial_year_id', 'doc_type'],
        'why' => 'Transaction Master number series by year and type.',
    ],
];
