<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\User;
use App\Models\Role;
use App\Models\RoleMenu;
use Illuminate\Database\Seeder;

class AccessSeeder extends Seeder
{
    /** key => [parent key|null, English, Marathi, route|null, icon, sort] */
    private const MENUS = [
        'dashboard' => [null, 'Dashboard', 'डॅशबोर्ड', 'dashboard', 'home', 1],

        // Masters — accordion; 2nd level = section labels (Books / Manage Item) like TMS
        'g_masters' => [null, 'Masters', 'मास्टर्स', null, 'masters', 10],

        'g_master_books' => ['g_masters', 'Books', 'बुक्स', null, 'book', 11],
        'financial_years' => ['g_master_books', 'Financial Year', 'आर्थिक वर्ष', 'financial-years.index', 'calendar', 12],
        'document_series' => ['g_master_books', 'Transaction Master', 'व्यवहार मास्टर', 'document-series.index', 'hash', 13],
        'books' => ['g_master_books', 'Ledger Master', 'लेजर मास्टर', 'books.index', 'ledger', 14],
        'clients' => ['g_master_books', 'Customers', 'ग्राहक', 'clients.index', 'users', 15],
        'suppliers' => ['g_master_books', 'Suppliers', 'पुरवठादार', 'suppliers.index', 'truck', 16],
        'areas' => ['g_master_books', 'Areas', 'क्षेत्र / गावगट', 'areas.index', 'globe', 17],

        'g_manage_items' => ['g_masters', 'Manage Item', 'आयटम व्यवस्थापन', null, 'item', 20],
        'products' => ['g_manage_items', 'Item Master', 'आयटम मास्टर', 'products.index', 'item', 21],
        'categories' => ['g_manage_items', 'Categories', 'वर्ग', 'categories.index', 'tag', 22],
        'manufacturers' => ['g_manage_items', 'Manufacturers', 'उत्पादक', 'manufacturers.index', 'factory', 23],
        'units' => ['g_manage_items', 'Units', 'एकके', 'units.index', 'list', 24],
        'hsn_codes' => ['g_manage_items', 'HSN / SAC', 'एचएसएन / एसएसी', 'hsn-codes.index', 'hash', 25],
        'gst_slabs' => ['g_manage_items', 'GST Slabs', 'जीएसटी स्लॅब', 'gst-slabs.index', 'rupee', 26],

        // Transactions — accordion; 2nd level = Purchase / Sales / Stock Journal
        'g_transactions' => [null, 'Transactions', 'व्यवहार', null, 'doc', 25],

        'g_txn_purchase' => ['g_transactions', 'Purchase', 'खरेदी', null, 'purchase', 26],
        'purchase_orders' => ['g_txn_purchase', 'Purchase Order', 'खरेदी ऑर्डर', 'purchase-orders.index', 'doc', 26],
        'purchases' => ['g_txn_purchase', 'Purchases', 'खरेदी बील', 'purchases.index', 'purchase', 27],
        'purchase_returns' => ['g_txn_purchase', 'Purchase Return', 'खरेदी परतावा', 'purchase-returns.index', 'return', 28],
        'payments' => ['g_txn_purchase', 'Payment', 'पेमेंट (पुरवठादार)', 'payments.index', 'payment', 29],
        'bulk_payment' => ['g_txn_purchase', 'Bulk Payment', 'बल्क पेमेंट', 'bulk-payments.create', 'payment', 30],

        'g_txn_sales' => ['g_transactions', 'Sales', 'विक्री', null, 'sales', 31],
        'sales' => ['g_txn_sales', 'Sales', 'विक्री बील', 'sales.index', 'sales', 32],
        'sale_returns' => ['g_txn_sales', 'Sale Return', 'विक्री परतावा', 'sale-returns.index', 'return', 33],
        'receipts' => ['g_txn_sales', 'Receipt', 'रसीद (ग्राहक)', 'receipts.index', 'receipt', 34],
        'bulk_receipt' => ['g_txn_sales', 'Bulk Receipt', 'बल्क रसीद', 'bulk-receipts.create', 'receipt', 35],

        'g_txn_accounts' => ['g_transactions', 'Account vouchers', 'खाते व्हाउचर', null, 'book', 36],
        'account_payments' => ['g_txn_accounts', 'Payment voucher', 'पेमेंट व्हाउचर', 'account-vouchers.payment', 'payment', 37],
        'account_receipts' => ['g_txn_accounts', 'Receipt voucher', 'रसीद व्हाउचर', 'account-vouchers.receipt', 'receipt', 38],
        'account_journals' => ['g_txn_accounts', 'Journal voucher', 'जर्नल व्हाउचर', 'account-vouchers.journal', 'doc', 39],
        'account_contras' => ['g_txn_accounts', 'Contra voucher', 'कॉन्ट्रा व्हाउचर', 'account-vouchers.contra', 'payment', 40],

        'g_txn_stock' => ['g_transactions', 'Stock Journal', 'साठा जर्नल', null, 'stock', 45],
        'opening_stock' => ['g_txn_stock', 'Opening Stock', 'प्रारंभिक साठा', 'opening-stock.index', 'stock', 46],
        'stock_transfer' => ['g_txn_stock', 'Stock Transfer', 'साठा हस्तांतरण', 'stock-journals.transfer', 'truck', 47],
        'stock_journal' => ['g_txn_stock', 'Stock Journal', 'साठा जर्नल', 'stock-journals.journal', 'doc', 48],
        'stock_rejection' => ['g_txn_stock', 'Stock Rejection', 'साठा नाकारणे', 'stock-journals.rejection', 'alert', 49],
        'physical_stock' => ['g_txn_stock', 'Physical Stock', 'भौतिक साठा', 'stock-journals.physical', 'list', 50],
        'stock_return' => ['g_txn_stock', 'Stock Return', 'साठा परतावा', 'stock-journals.return', 'return', 51],
        'stock_issue' => ['g_txn_stock', 'Stock Issue', 'साठा निर्गम', 'stock-journals.issue', 'box', 52],
        'put_away' => ['g_txn_stock', 'Put Away', 'रॅक ठेवणे', 'put-away.index', 'box', 53],

        // Reports — accordion; 2nd level = Accounts / Stock / Registers / Export
        'g_reports' => [null, 'Reports', 'अहवाल', null, 'report', 60],

        'g_report_accounts' => ['g_reports', 'Accounts', 'खाते', null, 'ledger', 61],
        'report_party_ledger' => ['g_report_accounts', 'Ledger Report', 'लेजर अहवाल', 'reports.party-ledger', 'ledger', 62],
        'report_outstanding' => ['g_report_accounts', 'Outstanding Report', 'थकबाकी अहवाल', 'reports.outstanding', 'outstanding', 63],
        'report_day_book' => ['g_report_accounts', 'Day Book', 'दिनपुस्तक', 'reports.day-book', 'book', 64],
        'report_cash_book' => ['g_report_accounts', 'Cash / Bank book', 'रोख / बँक बुक', 'reports.cash-book', 'rupee', 65],
        'report_trial_balance' => ['g_report_accounts', 'Trial Balance', 'ट्रायल बॅलन्स', 'reports.trial-balance', 'ledger', 66],
        'report_balance_sheet' => ['g_report_accounts', 'Balance Sheet', 'ताळेबंद', 'reports.balance-sheet', 'book', 67],
        'report_profit_loss' => ['g_report_accounts', 'Profit & Loss', 'नफा-तोटा', 'reports.profit-loss', 'rupee', 68],

        'g_report_stock' => ['g_reports', 'Stock & prices', 'साठा व दर', null, 'stock', 69],
        'report_stock' => ['g_report_stock', 'Stock Report', 'साठा अहवाल', 'reports.stock', 'stock', 70],
        'stock' => ['g_report_stock', 'Stock List', 'साठा यादी', 'stock.index', 'box', 71],
        'report_price_list' => ['g_report_stock', 'Price List', 'दरपत्रक', 'reports.price-list', 'price', 72],

        'g_report_registers' => ['g_reports', 'Registers', 'नोंदवही', null, 'chart', 73],
        'report_sales_register' => ['g_report_registers', 'Sales Register', 'विक्री नोंदवही', 'reports.sales-register', 'chart', 74],
        'report_purchase_register' => ['g_report_registers', 'Purchase Register', 'खरेदी नोंदवही', 'reports.purchase-register', 'chart', 75],

        'g_report_gst' => ['g_reports', 'GST', 'जीएसटी', null, 'chart', 76],
        'report_gst_return' => ['g_report_gst', 'GST Returns', 'जीएसटी रिटर्न', 'reports.gst-return', 'chart', 77],
        'report_gst_exception' => ['g_report_gst', 'GST Exceptions', 'जीएसटी अपवाद', 'reports.gst-exception', 'alert', 78],

        'g_report_export' => ['g_reports', 'Export', 'एक्सपोर्ट', null, 'doc', 79],
        'report_tally_export' => ['g_report_export', 'Export / Import (Tally)', 'एक्सपोर्ट / इंपोर्ट (टॅली)', 'reports.tally-export', 'doc', 80],

        'g_settings' => [null, 'Settings', 'सेटिंग्ज', null, 'cog', 65],
        'settings_company' => ['g_settings', 'Shop Registration', 'दुकान नोंदणी', 'settings.company', 'building', 66],
        'settings_app_control' => ['g_settings', 'Owner / App control', 'मालक / अॅप नियंत्रण', 'settings.app-control', 'cog', 67],
        'settings_mail' => ['g_settings', 'Mail (SMTP)', 'ईमेल (SMTP)', 'settings.mail', 'mail', 68],
        'settings_account_maps' => ['g_settings', 'Account Map', 'खाते नकाशा', 'settings.account-maps', 'link', 69],
        'settings_inventory_maps' => ['g_settings', 'Inventory Map', 'साठा नकाशा', 'settings.inventory-maps', 'link', 70],

        'g_admin' => [null, 'Administration', 'प्रशासन', null, 'shield', 80],
        'users' => ['g_admin', 'Staff & Users', 'कर्मचारी व वापरकर्ते', 'users.index', 'users', 81],
        'roles' => ['g_admin', 'Roles & Permissions', 'भूमिका व परवानग्या', 'roles.index', 'shield', 82],
        'menus' => ['g_admin', 'Menus', 'मेनू', 'menus.index', 'menu', 83],
        'godowns' => ['g_admin', 'Godowns', 'गोदामे', 'godowns.index', 'building', 84],
        'racks' => ['g_admin', 'Racks / Locations', 'रॅक / स्थान', 'racks.index', 'box', 85],
        'maintenance' => ['g_admin', 'Server maintenance', 'सर्व्हर देखभाल', 'maintenance.index', 'cog', 86],
    ];

    /** role slug => menu key => permission letters (v=view a=add e=edit d=delete) */
    private const GRANTS = [
        'cashier' => [
            'dashboard' => 'v',
            'clients' => 'vae',
            'areas' => 'v',
            'products' => 'v',
            'sales' => 'va',
            'receipts' => 'va',
            'bulk_receipt' => 'va',
            'sale_returns' => 'va',
            'account_receipts' => 'va',
            'account_payments' => 'va',
            'account_journals' => 'va',
            'account_contras' => 'va',
            'report_party_ledger' => 'v',
            'report_stock' => 'v',
            'stock' => 'v',
            'report_price_list' => 'v',
            'report_outstanding' => 'v',
            'report_day_book' => 'v',
            'report_cash_book' => 'v',
            'report_trial_balance' => 'v',
            'report_balance_sheet' => 'v',
            'report_sales_register' => 'v',
            'report_gst_return' => 'v',
            'report_gst_exception' => 'v',
            'report_tally_export' => 'v',
        ],
        'storekeeper' => [
            'dashboard' => 'v',
            'suppliers' => 'vae',
            'products' => 'vae',
            'categories' => 'vae',
            'manufacturers' => 'vae',
            'units' => 'vae',
            'hsn_codes' => 'vae',
            'gst_slabs' => 'vae',
            'purchase_orders' => 'vaed',
            'purchases' => 'vae',
            'purchase_returns' => 'va',
            'payments' => 'va',
            'bulk_payment' => 'va',
            'account_payments' => 'va',
            'account_contras' => 'va',
            'opening_stock' => 'vaed',
            'put_away' => 'vaed',
            'stock_transfer' => 'vaed',
            'stock_journal' => 'vaed',
            'stock_rejection' => 'vaed',
            'stock_issue' => 'vaed',
            'physical_stock' => 'vaed',
            'stock_return' => 'vaed',
            'report_stock' => 'v',
            'stock' => 'v',
            'report_price_list' => 'v',
            'report_purchase_register' => 'v',
            'report_gst_return' => 'v',
            'report_gst_exception' => 'v',
            'racks' => 'vae',
        ],
    ];

    public function run(): void
    {
        $roles = [
            'owner' => ['Owner', 'मालक', 'Full access to everything'],
            'cashier' => ['Cashier', 'रोखपाल', 'Billing and customer handling'],
            'storekeeper' => ['Storekeeper', 'भांडारपाल', 'Stock, purchases and product masters'],
        ];
        $roleModels = [];
        foreach ($roles as $slug => [$name, $mr, $desc]) {
            $roleModels[$slug] = Role::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'name_mr' => $mr, 'description' => $desc, 'is_system' => true],
            );
        }

        $menuModels = [];
        foreach (self::MENUS as $key => [$parent, $en, $mr, $route, $icon, $sort]) {
            $menuModels[$key] = Menu::updateOrCreate(['key' => $key], [
                'parent_id' => $parent ? $menuModels[$parent]->id : null,
                'label_en' => $en,
                'label_mr' => $mr,
                'route_name' => $route,
                'icon' => $icon,
                'sort_order' => $sort,
                'is_active' => true,
            ]);
        }

        // Soft-hide only obsolete top-level groups from older seeds (keep Books / Manage Item / txn folders).
        Menu::whereIn('key', ['g_inventory', 'g_sales', 'g_books', 'g_accounts'])->update(['is_active' => false]);
        // Older combined "Registers & GST" parent is replaced by Registers + GST groups above.

        foreach ($menuModels as $menu) {
            if (! $menu->route_name) {
                continue;
            }
            RoleMenu::updateOrCreate(
                ['role_id' => $roleModels['owner']->id, 'menu_id' => $menu->id],
                ['can_view' => true, 'can_add' => true, 'can_edit' => true, 'can_delete' => true],
            );
        }

        foreach (self::GRANTS as $slug => $grants) {
            foreach ($grants as $key => $letters) {
                if (! isset($menuModels[$key])) {
                    continue;
                }
                RoleMenu::updateOrCreate(
                    ['role_id' => $roleModels[$slug]->id, 'menu_id' => $menuModels[$key]->id],
                    [
                        'can_view' => str_contains($letters, 'v'),
                        'can_add' => str_contains($letters, 'a'),
                        'can_edit' => str_contains($letters, 'e'),
                        'can_delete' => str_contains($letters, 'd'),
                    ],
                );
            }
        }

        User::forgetMenuCache();
    }
}
