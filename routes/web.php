<?php

use App\Http\Controllers\AccountReportController;
use App\Http\Controllers\AccountVoucherController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ClientStatementController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PartyOutreachController;
use App\Http\Controllers\DocumentNumberController;
use App\Http\Controllers\DocumentSeriesController;
use App\Http\Controllers\FinancialYearController;
use App\Http\Controllers\GodownController;
use App\Http\Controllers\GodownSwitchController;
use App\Http\Controllers\GstSlabController;
use App\Http\Controllers\HsnCodeController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\Setup\InstallController;
use App\Http\Controllers\ManufacturerController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\BulkPaymentController;
use App\Http\Controllers\BulkReceiptController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\PutAwayController;
use App\Http\Controllers\RackController;
use App\Http\Controllers\StockJournalController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleReturnController;
use App\Http\Controllers\Settings\AccountMapController;
use App\Http\Controllers\Settings\AppControlController;
use App\Http\Controllers\Settings\CompanyController;
use App\Http\Controllers\Settings\InventoryMapController;
use App\Http\Controllers\Settings\MailSettingController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\LiveStockController;
use App\Http\Controllers\PriceListController;
use App\Http\Controllers\TallyExportController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

// Language switch works on guest pages too (login / forgot password).
Route::get('locale/{locale}', LocaleController::class)->name('locale.switch');

// Public client statement (encrypted token — no login)
Route::get('s/{token}', [ClientStatementController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{32,64}')
    ->name('client-statement.show');

// Shop TV live stock — passkey only (no staff login)
Route::get('live-stock', [LiveStockController::class, 'gate'])->name('live-stock.gate');
Route::post('live-stock/unlock', [LiveStockController::class, 'unlock'])->name('live-stock.unlock');
Route::post('live-stock/lock', [LiveStockController::class, 'lock'])->name('live-stock.lock');
Route::get('live-stock/board', [LiveStockController::class, 'board'])->name('live-stock.board');
Route::get('live-stock/data', [LiveStockController::class, 'data'])->name('live-stock.data');

// First-time / live install (no SSH). Locked once installed with no pending migrations.
Route::get('install', [InstallController::class, 'show'])->name('setup.install');
Route::post('install', [InstallController::class, 'run'])->name('setup.install.run');

Route::middleware('auth')->group(function () {
    Route::middleware('menu:dashboard')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('dashboard/pulse', [DashboardController::class, 'pulse'])->name('dashboard.pulse');
        Route::get('dashboard/figures', [DashboardController::class, 'figures'])->name('dashboard.figures');
        Route::get('dashboard/recent', [DashboardController::class, 'recent'])->name('dashboard.recent');
        Route::get('dashboard/alerts/low-stock', [DashboardController::class, 'lowStockAlert'])->name('dashboard.alerts.low-stock');
        Route::get('dashboard/alerts/expiry', [DashboardController::class, 'expiryAlert'])->name('dashboard.alerts.expiry');
        Route::get('dashboard/alerts/outstanding', [DashboardController::class, 'outstandingAlert'])->name('dashboard.alerts.outstanding');
        Route::get('dashboard/calendar', [DashboardController::class, 'calendar'])->name('dashboard.calendar');
    });

    Route::post('party/{ledger}/outreach', [PartyOutreachController::class, 'compose'])->name('party.outreach');

    Route::post('godown/switch', GodownSwitchController::class)->name('godown.switch');

    // Profile (own account, no menu permission needed)
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');

    // ---- Masters -------------------------------------------------------
    // Clients, suppliers and account books share LedgerController (kind = route name prefix).
    foreach (['clients', 'suppliers', 'books'] as $kind) {
        Route::middleware("menu:$kind")->prefix($kind)->name("$kind.")->group(function () {
            Route::get('/', [LedgerController::class, 'index'])->name('index');
            Route::get('create', [LedgerController::class, 'create'])->name('create');
            Route::post('/', [LedgerController::class, 'store'])->name('store');
            Route::get('{ledger}/edit', [LedgerController::class, 'edit'])->name('edit');
            Route::put('{ledger}', [LedgerController::class, 'update'])->name('update');
            Route::delete('{ledger}', [LedgerController::class, 'destroy'])->name('destroy');
        });
    }

    Route::resource('areas', AreaController::class)->except('show')->middleware('menu:areas');
    Route::resource('manufacturers', ManufacturerController::class)->except('show')->middleware('menu:manufacturers');
    Route::resource('categories', CategoryController::class)->except('show')->middleware('menu:categories');
    Route::resource('units', UnitController::class)->except('show')->middleware('menu:units');
    Route::resource('hsn-codes', HsnCodeController::class)->except('show')->middleware('menu:hsn_codes');
    Route::resource('gst-slabs', GstSlabController::class)->except('show')->middleware('menu:gst_slabs');
    Route::middleware('menu:products')->group(function () {
        Route::get('products/bulk', [ProductController::class, 'bulkCreate'])->middleware('menu:products,add')->name('products.bulk');
        Route::post('products/bulk', [ProductController::class, 'bulkStore'])->middleware('menu:products,add')->name('products.bulk.store');
    });
    Route::resource('products', ProductController::class)->except('show')->middleware('menu:products');

    // ---- Inventory -----------------------------------------------------
    Route::middleware('menu:stock')->prefix('stock')->name('stock.')->group(function () {
        Route::get('/', [StockController::class, 'index'])->name('index');
        Route::get('low', [StockController::class, 'low'])->name('low');
        Route::get('preview', [StockController::class, 'preview'])->name('preview');
        Route::get('{product}/movements', [StockController::class, 'movements'])->name('movements');
    });

    Route::middleware('menu:opening_stock')->prefix('opening-stock')->name('opening-stock.')->group(function () {
        Route::get('/', [StockController::class, 'openingIndex'])->name('index');
        Route::get('bulk', [StockController::class, 'openingBulk'])->middleware('menu:opening_stock,add')->name('bulk');
        Route::get('create', [StockController::class, 'openingCreate'])->middleware('menu:opening_stock,add')->name('create');
        Route::post('/', [StockController::class, 'openingStore'])->middleware('menu:opening_stock,add')->name('store');
    });

    Route::middleware('menu:purchase_orders')->prefix('purchase-orders')->name('purchase-orders.')->group(function () {
        Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
        Route::get('browse', [PurchaseOrderController::class, 'browse'])->name('browse');
        Route::post('/', [PurchaseOrderController::class, 'store'])->middleware('menu:purchase_orders,add')->name('store');
        Route::get('{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('show');
        Route::get('{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])->middleware('menu:purchase_orders,edit')->name('edit');
        Route::put('{purchaseOrder}', [PurchaseOrderController::class, 'update'])->middleware('menu:purchase_orders,edit')->name('update');
        Route::get('{purchaseOrder}/convert', [PurchaseOrderController::class, 'convert'])->name('convert');
        Route::delete('{purchaseOrder}', [PurchaseOrderController::class, 'cancel'])->middleware('menu:purchase_orders,delete')->name('cancel');
    });

    Route::middleware('menu:purchases')->prefix('purchases')->name('purchases.')->group(function () {
        Route::get('/', [PurchaseController::class, 'index'])->name('index');
        Route::get('browse', [PurchaseController::class, 'browse'])->name('browse');
        Route::get('create', [PurchaseController::class, 'create'])->name('create');
        Route::post('/', [PurchaseController::class, 'store'])->name('store');
        Route::get('{purchase}', [PurchaseController::class, 'show'])->name('show');
        Route::get('{purchase}/details', [PurchaseController::class, 'details'])->name('details');
        Route::post('{purchase}/payments', [PurchaseController::class, 'payment'])->name('payments.store');
        Route::delete('{purchase}', [PurchaseController::class, 'cancel'])->name('cancel');
    });

    Route::middleware('menu:purchase_returns')->prefix('purchase-returns')->name('purchase-returns.')->group(function () {
        Route::get('/', [PurchaseReturnController::class, 'index'])->name('index');
        Route::get('create', [PurchaseReturnController::class, 'create'])->middleware('menu:purchase_returns,add')->name('create');
        Route::post('/', [PurchaseReturnController::class, 'store'])->middleware('menu:purchase_returns,add')->name('store');
        Route::get('{purchaseReturn}', [PurchaseReturnController::class, 'show'])->name('show');
    });

    // ---- Sales ---------------------------------------------------------
    Route::middleware('menu:sales')->prefix('sales')->name('sales.')->group(function () {
        Route::get('/', [SaleController::class, 'index'])->name('index');
        Route::get('browse', [SaleController::class, 'browse'])->name('browse');
        Route::get('create', [SaleController::class, 'create'])->name('create');
        Route::post('/', [SaleController::class, 'store'])->name('store');
        Route::get('{sale}', [SaleController::class, 'show'])->name('show');
        Route::get('{sale}/details', [SaleController::class, 'details'])->name('details');
        Route::get('{sale}/pdf', [SaleController::class, 'pdf'])->name('pdf');
        Route::get('{sale}/print', [SaleController::class, 'printPreview'])->name('print');
        Route::get('{sale}/dc', [SaleController::class, 'dc'])->name('dc');
        Route::post('{sale}/payments', [SaleController::class, 'payment'])->name('payments.store');
        Route::delete('{sale}', [SaleController::class, 'cancel'])->name('cancel');
    });

    Route::middleware('menu:sale_returns')->prefix('sale-returns')->name('sale-returns.')->group(function () {
        Route::get('/', [SaleReturnController::class, 'index'])->name('index');
        Route::get('create', [SaleReturnController::class, 'create'])->name('create');
        Route::post('/', [SaleReturnController::class, 'store'])->name('store');
        Route::get('{saleReturn}', [SaleReturnController::class, 'show'])->name('show');
    });

    // ---- Stock journals (transfer / journal / rejection / issue / physical / return) ----
    Route::prefix('stock-journals')->name('stock-journals.')->group(function () {
        Route::get('v/{stockJournal}', [StockJournalController::class, 'show'])->name('show');
        Route::get('v/{stockJournal}/edit', [StockJournalController::class, 'edit'])->name('edit');
        Route::put('v/{stockJournal}', [StockJournalController::class, 'update'])->name('update');
        Route::delete('v/{stockJournal}', [StockJournalController::class, 'cancel'])->name('cancel');

        Route::get('transfer', [StockJournalController::class, 'index'])->defaults('type', 'transfer')->middleware('menu:stock_transfer')->name('transfer');
        Route::get('transfer/browse', [StockJournalController::class, 'browse'])->defaults('type', 'transfer')->middleware('menu:stock_transfer')->name('transfer.browse');
        Route::post('transfer', [StockJournalController::class, 'store'])->defaults('type', 'transfer')->middleware('menu:stock_transfer,add')->name('transfer.store');

        Route::get('journal', [StockJournalController::class, 'index'])->defaults('type', 'journal')->middleware('menu:stock_journal')->name('journal');
        Route::get('journal/browse', [StockJournalController::class, 'browse'])->defaults('type', 'journal')->middleware('menu:stock_journal')->name('journal.browse');
        Route::post('journal', [StockJournalController::class, 'store'])->defaults('type', 'journal')->middleware('menu:stock_journal,add')->name('journal.store');

        Route::get('rejection', [StockJournalController::class, 'index'])->defaults('type', 'rejection')->middleware('menu:stock_rejection')->name('rejection');
        Route::get('rejection/browse', [StockJournalController::class, 'browse'])->defaults('type', 'rejection')->middleware('menu:stock_rejection')->name('rejection.browse');
        Route::post('rejection', [StockJournalController::class, 'store'])->defaults('type', 'rejection')->middleware('menu:stock_rejection,add')->name('rejection.store');

        Route::get('issue', [StockJournalController::class, 'index'])->defaults('type', 'issue')->middleware('menu:stock_issue')->name('issue');
        Route::get('issue/browse', [StockJournalController::class, 'browse'])->defaults('type', 'issue')->middleware('menu:stock_issue')->name('issue.browse');
        Route::post('issue', [StockJournalController::class, 'store'])->defaults('type', 'issue')->middleware('menu:stock_issue,add')->name('issue.store');

        Route::get('physical', [StockJournalController::class, 'index'])->defaults('type', 'physical')->middleware('menu:physical_stock')->name('physical');
        Route::get('physical/browse', [StockJournalController::class, 'browse'])->defaults('type', 'physical')->middleware('menu:physical_stock')->name('physical.browse');
        Route::post('physical', [StockJournalController::class, 'store'])->defaults('type', 'physical')->middleware('menu:physical_stock,add')->name('physical.store');

        Route::get('return', [StockJournalController::class, 'index'])->defaults('type', 'return')->middleware('menu:stock_return')->name('return');
        Route::get('return/browse', [StockJournalController::class, 'browse'])->defaults('type', 'return')->middleware('menu:stock_return')->name('return.browse');
        Route::post('return', [StockJournalController::class, 'store'])->defaults('type', 'return')->middleware('menu:stock_return,add')->name('return.store');
    });

    Route::middleware('menu:receipts')->prefix('receipts')->name('receipts.')->group(function () {
        Route::get('/', [ReceiptController::class, 'index'])->name('index');
        Route::post('/', [ReceiptController::class, 'store'])->name('store');
    });

    Route::middleware('menu:bulk_receipt')->prefix('bulk-receipts')->name('bulk-receipts.')->group(function () {
        Route::get('/', [BulkReceiptController::class, 'create'])->name('create');
        Route::post('/', [BulkReceiptController::class, 'store'])->middleware('menu:bulk_receipt,add')->name('store');
    });

    Route::middleware('menu:payments')->prefix('payments')->name('payments.')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])->name('index');
        Route::post('/', [PaymentController::class, 'store'])->name('store');
    });

    Route::middleware('menu:bulk_payment')->prefix('bulk-payments')->name('bulk-payments.')->group(function () {
        Route::get('/', [BulkPaymentController::class, 'create'])->name('create');
        Route::post('/', [BulkPaymentController::class, 'store'])->middleware('menu:bulk_payment,add')->name('store');
    });

    // Account vouchers (payment / receipt / journal / contra) — Tally-like, kept simple
    Route::prefix('account-vouchers')->name('account-vouchers.')->group(function () {
        Route::get('/', [AccountVoucherController::class, 'register'])->name('register');
        Route::delete('{accountVoucher}', [AccountVoucherController::class, 'cancel'])->name('cancel');

        Route::get('payment', [AccountVoucherController::class, 'index'])->defaults('kind', 'payment')->middleware('menu:account_payments')->name('payment');
        Route::post('payment', [AccountVoucherController::class, 'store'])->defaults('kind', 'payment')->middleware('menu:account_payments,add')->name('payment.store');
        Route::get('receipt', [AccountVoucherController::class, 'index'])->defaults('kind', 'receipt')->middleware('menu:account_receipts')->name('receipt');
        Route::post('receipt', [AccountVoucherController::class, 'store'])->defaults('kind', 'receipt')->middleware('menu:account_receipts,add')->name('receipt.store');
        Route::get('journal', [AccountVoucherController::class, 'index'])->defaults('kind', 'journal')->middleware('menu:account_journals')->name('journal');
        Route::post('journal', [AccountVoucherController::class, 'store'])->defaults('kind', 'journal')->middleware('menu:account_journals,add')->name('journal.store');
        Route::get('contra', [AccountVoucherController::class, 'index'])->defaults('kind', 'contra')->middleware('menu:account_contras')->name('contra');
        Route::post('contra', [AccountVoucherController::class, 'store'])->defaults('kind', 'contra')->middleware('menu:account_contras,add')->name('contra.store');
    });

    // ---- Accounts reports ---------------------------------------------
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('party-ledger', [AccountReportController::class, 'partyLedger'])->middleware('menu:report_party_ledger')->name('party-ledger');
        Route::get('stock', [AccountReportController::class, 'stock'])->middleware('menu:report_stock')->name('stock');
        Route::get('price-list', [PriceListController::class, 'index'])->middleware('menu:report_price_list')->name('price-list');
        Route::post('price-list', [PriceListController::class, 'update'])->middleware('menu:report_price_list')->name('price-list.update');
        Route::get('outstanding', [AccountReportController::class, 'outstanding'])->middleware('menu:report_outstanding')->name('outstanding');
        Route::post('outstanding/interest', [AccountReportController::class, 'applyInterest'])->middleware('menu:report_outstanding')->name('outstanding.interest');
        Route::get('day-book', [AccountReportController::class, 'dayBook'])->middleware('menu:report_day_book')->name('day-book');
        Route::get('cash-book', [AccountReportController::class, 'cashBook'])->middleware('menu:report_cash_book')->name('cash-book');
        Route::get('trial-balance', [AccountReportController::class, 'trialBalance'])->middleware('menu:report_trial_balance')->name('trial-balance');
        Route::get('balance-sheet', [AccountReportController::class, 'balanceSheet'])->middleware('menu:report_balance_sheet')->name('balance-sheet');
        Route::get('sales-register', [AccountReportController::class, 'salesRegister'])->middleware('menu:report_sales_register')->name('sales-register');
        Route::get('purchase-register', [AccountReportController::class, 'purchaseRegister'])->middleware('menu:report_purchase_register')->name('purchase-register');
        Route::get('profit-loss', [AccountReportController::class, 'profitLoss'])->middleware('menu:report_profit_loss')->name('profit-loss');
        Route::get('gst-return', [AccountReportController::class, 'gstReturn'])->middleware('menu:report_gst_return')->name('gst-return');
        Route::get('gst-exception', [AccountReportController::class, 'gstException'])->middleware('menu:report_gst_exception')->name('gst-exception');
        Route::get('tally-export', [TallyExportController::class, 'index'])->middleware('menu:report_tally_export')->name('tally-export');
        Route::get('tally-export/download', [TallyExportController::class, 'download'])->middleware('menu:report_tally_export')->name('tally-export.download');
        Route::post('tally-export/import', [TallyExportController::class, 'import'])->middleware('menu:report_tally_export')->name('tally-export.import');
    });

    // ---- Settings ------------------------------------------------------
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('company', [CompanyController::class, 'edit'])->middleware('menu:settings_company')->name('company');
        Route::put('company', [CompanyController::class, 'update'])->middleware('menu:settings_company,edit')->name('company.update');

        Route::get('app-control', [AppControlController::class, 'edit'])->middleware('menu:settings_app_control')->name('app-control');
        Route::put('app-control', [AppControlController::class, 'update'])->middleware('menu:settings_app_control,edit')->name('app-control.update');

        Route::get('mail', [MailSettingController::class, 'edit'])->middleware('menu:settings_mail')->name('mail');
        Route::put('mail', [MailSettingController::class, 'update'])->middleware('menu:settings_mail,edit')->name('mail.update');
        Route::post('mail/test', [MailSettingController::class, 'test'])->middleware('menu:settings_mail,edit')->name('mail.test');

        Route::get('account-maps', [AccountMapController::class, 'edit'])->middleware('menu:settings_account_maps')->name('account-maps');
        Route::put('account-maps', [AccountMapController::class, 'update'])->middleware('menu:settings_account_maps,edit')->name('account-maps.update');

        Route::get('inventory-maps', [InventoryMapController::class, 'index'])->middleware('menu:settings_inventory_maps')->name('inventory-maps');
        Route::post('inventory-maps', [InventoryMapController::class, 'store'])->middleware('menu:settings_inventory_maps,add')->name('inventory-maps.store');
        Route::delete('inventory-maps/{inventoryMap}', [InventoryMapController::class, 'destroy'])->middleware('menu:settings_inventory_maps,delete')->name('inventory-maps.destroy');
    });

    // AJAX: preview next voucher number from Transaction Master (no increment)
    Route::get('document-numbers/peek', [DocumentNumberController::class, 'peek'])->name('document-numbers.peek');

    // ---- Financial books (FY + Transaction Master) --------------------
    Route::middleware('menu:financial_years')->group(function () {
        Route::resource('financial-years', FinancialYearController::class)->except('show');
        Route::post('financial-years/{financial_year}/activate', [FinancialYearController::class, 'activate'])->middleware('menu:financial_years,edit')->name('financial-years.activate');
        Route::post('financial-years-create-next', [FinancialYearController::class, 'createNext'])->middleware('menu:financial_years,add')->name('financial-years.create-next');
    });
    Route::middleware('menu:document_series')->group(function () {
        Route::get('document-series', [DocumentSeriesController::class, 'index'])->name('document-series.index');
        Route::get('document-series/create', [DocumentSeriesController::class, 'create'])->middleware('menu:document_series,add')->name('document-series.create');
        Route::post('document-series', [DocumentSeriesController::class, 'store'])->middleware('menu:document_series,add')->name('document-series.store');
        Route::get('document-series/{documentSeries}/edit', [DocumentSeriesController::class, 'edit'])->name('document-series.edit');
        Route::put('document-series/{documentSeries}', [DocumentSeriesController::class, 'update'])->name('document-series.update');
        Route::delete('document-series/{documentSeries}', [DocumentSeriesController::class, 'destroy'])->middleware('menu:document_series,delete')->name('document-series.destroy');
        Route::post('document-series/seed', [DocumentSeriesController::class, 'seed'])->middleware('menu:document_series,add')->name('document-series.seed');
    });

    // ---- Administration ------------------------------------------------
    Route::middleware('menu:users')->group(function () {
        Route::resource('users', UserController::class)->except('show');
        Route::get('users/{user}/password', [UserController::class, 'passwordForm'])->middleware('menu:users,edit')->name('users.password');
        Route::put('users/{user}/password', [UserController::class, 'setPassword'])->middleware('menu:users,edit')->name('users.password.update');
        Route::post('users/{user}/toggle', [UserController::class, 'toggleStatus'])->middleware('menu:users,edit')->name('users.toggle');
    });
    Route::resource('roles', RoleController::class)->except('show')->middleware('menu:roles');
    Route::resource('godowns', GodownController::class)->except('show')->middleware('menu:godowns');
    Route::resource('racks', RackController::class)->except('show')->middleware('menu:racks');

    Route::middleware('menu:put_away')->prefix('put-away')->name('put-away.')->group(function () {
        Route::get('/', [PutAwayController::class, 'index'])->name('index');
        Route::get('{batch}/edit', [PutAwayController::class, 'edit'])->middleware('menu:put_away,edit')->name('edit');
        Route::put('{batch}', [PutAwayController::class, 'update'])->middleware('menu:put_away,edit')->name('update');
    });
    Route::get('menus', [MenuController::class, 'index'])->middleware('menu:menus')->name('menus.index');
    Route::get('menus/{menu}/edit', [MenuController::class, 'edit'])->middleware('menu:menus')->name('menus.edit');
    Route::put('menus/{menu}', [MenuController::class, 'update'])->middleware('menu:menus')->name('menus.update');

    Route::middleware('menu:maintenance')->prefix('maintenance')->name('maintenance.')->group(function () {
        Route::get('/', [MaintenanceController::class, 'index'])->name('index');
        Route::post('deploy', [MaintenanceController::class, 'deploy'])->middleware('menu:maintenance,edit')->name('deploy');
        Route::post('clear-caches', [MaintenanceController::class, 'clearCaches'])->middleware('menu:maintenance,edit')->name('clear-caches');
        Route::post('indexes', [MaintenanceController::class, 'applyIndexes'])->middleware('menu:maintenance,edit')->name('indexes');
        Route::post('sync-masters', [MaintenanceController::class, 'syncMasters'])->middleware('menu:maintenance,edit')->name('sync-masters');
    });
});

require __DIR__.'/auth.php';
