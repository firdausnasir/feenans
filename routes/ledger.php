<?php

use App\Http\Controllers\Ledger\AccountController;
use App\Http\Controllers\Ledger\AccountTypeController;
use App\Http\Controllers\Ledger\ActivityLogController;
use App\Http\Controllers\Ledger\AttachmentController;
use App\Http\Controllers\Ledger\BillController;
use App\Http\Controllers\Ledger\BudgetController;
use App\Http\Controllers\Ledger\CategoryController;
use App\Http\Controllers\Ledger\DashboardController;
use App\Http\Controllers\Ledger\ImportController;
use App\Http\Controllers\Ledger\PayeeController;
use App\Http\Controllers\Ledger\ReportController;
use App\Http\Controllers\Ledger\SampleDataController;
use App\Http\Controllers\Ledger\SettingsController;
use App\Http\Controllers\Ledger\TagController;
use App\Http\Controllers\Ledger\TransactionController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\Settings\DataExportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->scopeBindings()->group(function () {
    Route::get('ledgers', [LedgerController::class, 'index'])->name('ledgers.index');
    Route::get('ledgers/create', [LedgerController::class, 'create'])->name('ledgers.create');
    Route::post('ledgers', [LedgerController::class, 'store'])->name('ledgers.store');
    Route::get('ledgers/{ledger}/edit', [LedgerController::class, 'edit'])->name('ledgers.edit');
    Route::patch('ledgers/{ledger}', [LedgerController::class, 'update'])->name('ledgers.update');
    Route::delete('ledgers/{ledger}', [LedgerController::class, 'destroy'])->name('ledgers.destroy');

    // Dashboard
    Route::get('ledgers/{ledger}', DashboardController::class)->name('ledgers.dashboard');

    // Budgets
    Route::get('ledgers/{ledger}/budgets', [BudgetController::class, 'index'])->name('ledgers.budgets.index');
    Route::post('ledgers/{ledger}/budgets', [BudgetController::class, 'store'])->name('ledgers.budgets.store');
    Route::put('ledgers/{ledger}/budgets/{budget}', [BudgetController::class, 'update'])->name('ledgers.budgets.update');
    Route::delete('ledgers/{ledger}/budgets/{budget}', [BudgetController::class, 'destroy'])->name('ledgers.budgets.destroy');

    // Import
    Route::get('ledgers/{ledger}/import', [ImportController::class, 'create'])->name('ledgers.import.create');
    Route::post('ledgers/{ledger}/import/parse', [ImportController::class, 'parse'])->name('ledgers.import.parse');
    Route::post('ledgers/{ledger}/import', [ImportController::class, 'store'])->name('ledgers.import.store');
    Route::get('ledgers/{ledger}/import/mappings', [ImportController::class, 'mappings'])->name('ledgers.import.mappings');
    Route::post('ledgers/{ledger}/import/mappings', [ImportController::class, 'saveMapping'])->name('ledgers.import.mappings.store');
    Route::delete('ledgers/{ledger}/import/mappings/{importMapping}', [ImportController::class, 'destroyMapping'])->name('ledgers.import.mappings.destroy');

    // Transactions — bulk operations and export must come before {transaction} wildcard routes
    Route::post('ledgers/{ledger}/transactions/bulk-update', [TransactionController::class, 'bulkUpdate'])
        ->name('ledgers.transactions.bulk-update');
    Route::post('ledgers/{ledger}/transactions/bulk-delete', [TransactionController::class, 'bulkDestroy'])
        ->name('ledgers.transactions.bulk-destroy');
    Route::get('ledgers/{ledger}/transactions/export', [TransactionController::class, 'export'])
        ->name('ledgers.transactions.export');
    Route::get('ledgers/{ledger}/transactions', [TransactionController::class, 'index'])
        ->name('ledgers.transactions.index');
    Route::post('ledgers/{ledger}/transactions', [TransactionController::class, 'store'])
        ->name('ledgers.transactions.store');
    Route::get('ledgers/{ledger}/transactions/{transaction}/edit', [TransactionController::class, 'edit'])
        ->name('ledgers.transactions.edit');
    Route::put('ledgers/{ledger}/transactions/{transaction}', [TransactionController::class, 'update'])
        ->name('ledgers.transactions.update');
    Route::delete('ledgers/{ledger}/transactions/{transaction}', [TransactionController::class, 'destroy'])
        ->name('ledgers.transactions.destroy');
    Route::get('ledgers/{ledger}/transactions/trash', [TransactionController::class, 'trash'])
        ->name('ledgers.transactions.trash');
    Route::patch('ledgers/{ledger}/transactions/trash/{transaction}/restore', [TransactionController::class, 'restore'])
        ->withTrashed()
        ->name('ledgers.transactions.restore');
    Route::delete('ledgers/{ledger}/transactions/trash/{transaction}', [TransactionController::class, 'forceDestroy'])
        ->withTrashed()
        ->name('ledgers.transactions.force-destroy');
    Route::post('ledgers/{ledger}/transactions/{transaction}/attachments', [AttachmentController::class, 'store'])
        ->name('ledgers.transactions.attachments.store');
    Route::get('ledgers/{ledger}/transactions/{transaction}/attachments/{attachment}', [AttachmentController::class, 'show'])
        ->name('ledgers.transactions.attachments.show');
    Route::delete('ledgers/{ledger}/transactions/{transaction}/attachments/{attachment}', [AttachmentController::class, 'destroy'])
        ->name('ledgers.transactions.attachments.destroy');

    // Accounts — reorder must come before {account} wildcard routes
    Route::post('ledgers/{ledger}/accounts/reorder', [AccountController::class, 'reorder'])
        ->name('ledgers.accounts.reorder');
    Route::get('ledgers/{ledger}/accounts', [AccountController::class, 'index'])
        ->name('ledgers.accounts.index');
    Route::get('ledgers/{ledger}/accounts/trash', [AccountController::class, 'trash'])
        ->name('ledgers.accounts.trash');
    Route::get('ledgers/{ledger}/accounts/create', [AccountController::class, 'create'])
        ->name('ledgers.accounts.create');
    Route::post('ledgers/{ledger}/accounts', [AccountController::class, 'store'])
        ->name('ledgers.accounts.store');
    Route::get('ledgers/{ledger}/accounts/{account}', [AccountController::class, 'show'])
        ->name('ledgers.accounts.show');
    Route::get('ledgers/{ledger}/accounts/{account}/export', [AccountController::class, 'export'])
        ->name('ledgers.accounts.export');
    Route::get('ledgers/{ledger}/accounts/{account}/edit', [AccountController::class, 'edit'])
        ->name('ledgers.accounts.edit');
    Route::put('ledgers/{ledger}/accounts/{account}', [AccountController::class, 'update'])
        ->name('ledgers.accounts.update');
    Route::delete('ledgers/{ledger}/accounts/{account}', [AccountController::class, 'destroy'])
        ->name('ledgers.accounts.destroy');
    Route::patch('ledgers/{ledger}/accounts/{account}/toggle-visibility', [AccountController::class, 'toggleVisibility'])
        ->name('ledgers.accounts.toggle-visibility');
    Route::patch('ledgers/{ledger}/accounts/trash/{account}/restore', [AccountController::class, 'restore'])
        ->withTrashed()
        ->name('ledgers.accounts.restore');
    Route::delete('ledgers/{ledger}/accounts/trash/{account}', [AccountController::class, 'forceDestroy'])
        ->withTrashed()
        ->name('ledgers.accounts.force-destroy');

    // Categories — reorder must come before {category} wildcard routes
    Route::post('ledgers/{ledger}/categories/reorder', [CategoryController::class, 'reorder'])
        ->name('ledgers.categories.reorder');
    Route::get('ledgers/{ledger}/categories', [CategoryController::class, 'index'])
        ->name('ledgers.categories.index');
    Route::get('ledgers/{ledger}/categories/create', [CategoryController::class, 'create'])
        ->name('ledgers.categories.create');
    Route::post('ledgers/{ledger}/categories', [CategoryController::class, 'store'])
        ->name('ledgers.categories.store');
    Route::put('ledgers/{ledger}/categories/{category}', [CategoryController::class, 'update'])
        ->name('ledgers.categories.update');
    Route::delete('ledgers/{ledger}/categories/{category}', [CategoryController::class, 'destroy'])
        ->name('ledgers.categories.destroy');
    Route::get('ledgers/{ledger}/categories/trash', [CategoryController::class, 'trash'])
        ->name('ledgers.categories.trash');
    Route::patch('ledgers/{ledger}/categories/trash/{category}/restore', [CategoryController::class, 'restore'])
        ->withTrashed()
        ->name('ledgers.categories.restore');
    Route::delete('ledgers/{ledger}/categories/trash/{category}', [CategoryController::class, 'forceDestroy'])
        ->withTrashed()
        ->name('ledgers.categories.force-destroy');

    // Bills
    Route::get('ledgers/{ledger}/bills', [BillController::class, 'index'])
        ->name('ledgers.bills.index');
    Route::get('ledgers/{ledger}/bills/create', [BillController::class, 'create'])
        ->name('ledgers.bills.create');
    Route::post('ledgers/{ledger}/bills', [BillController::class, 'store'])
        ->name('ledgers.bills.store');
    Route::get('ledgers/{ledger}/bills/{bill}/edit', [BillController::class, 'edit'])
        ->name('ledgers.bills.edit');
    Route::put('ledgers/{ledger}/bills/{bill}', [BillController::class, 'update'])
        ->name('ledgers.bills.update');
    Route::delete('ledgers/{ledger}/bills/{bill}', [BillController::class, 'destroy'])
        ->name('ledgers.bills.destroy');
    Route::get('ledgers/{ledger}/bills/trash', [BillController::class, 'trash'])
        ->name('ledgers.bills.trash');
    Route::patch('ledgers/{ledger}/bills/trash/{bill}/restore', [BillController::class, 'restore'])
        ->withTrashed()
        ->name('ledgers.bills.restore');
    Route::delete('ledgers/{ledger}/bills/trash/{bill}', [BillController::class, 'forceDestroy'])
        ->withTrashed()
        ->name('ledgers.bills.force-destroy');
    Route::post('ledgers/{ledger}/bills/{bill}/pay', [BillController::class, 'pay'])
        ->name('ledgers.bills.pay');
    Route::patch('ledgers/{ledger}/bills/{bill}/toggle', [BillController::class, 'toggle'])
        ->name('ledgers.bills.toggle');

    // Payees — merge must come before {payee} wildcard routes
    Route::post('ledgers/{ledger}/payees/merge', [PayeeController::class, 'merge'])
        ->name('ledgers.payees.merge');
    Route::get('ledgers/{ledger}/payees', [PayeeController::class, 'index'])
        ->name('ledgers.payees.index');
    Route::post('ledgers/{ledger}/payees', [PayeeController::class, 'store'])
        ->name('ledgers.payees.store');
    Route::put('ledgers/{ledger}/payees/{payee}', [PayeeController::class, 'update'])
        ->name('ledgers.payees.update');
    Route::delete('ledgers/{ledger}/payees/{payee}', [PayeeController::class, 'destroy'])
        ->name('ledgers.payees.destroy');

    // Tags
    Route::get('ledgers/{ledger}/tags', [TagController::class, 'index'])->name('ledgers.tags.index');
    Route::post('ledgers/{ledger}/tags', [TagController::class, 'store'])->name('ledgers.tags.store');
    Route::put('ledgers/{ledger}/tags/{tag}', [TagController::class, 'update'])->name('ledgers.tags.update');
    Route::delete('ledgers/{ledger}/tags/{tag}', [TagController::class, 'destroy'])->name('ledgers.tags.destroy');

    // Reports
    Route::get('ledgers/{ledger}/reports', [ReportController::class, 'index'])
        ->name('ledgers.reports.index');
    Route::get('ledgers/{ledger}/reports/export-pdf', [ReportController::class, 'exportPdf'])
        ->name('ledgers.reports.export-pdf');
    Route::get('ledgers/{ledger}/activity', [ActivityLogController::class, 'index'])
        ->name('ledgers.activity.index');

    // Settings
    Route::get('ledgers/{ledger}/settings', [SettingsController::class, 'index'])
        ->name('ledgers.settings.index');
    Route::put('ledgers/{ledger}/settings', [SettingsController::class, 'update'])
        ->name('ledgers.settings.update');

    // Data Export
    Route::get('ledgers/{ledger}/export', DataExportController::class)
        ->name('ledgers.export');

    // Account Types — reorder must come before {accountType} wildcard routes
    Route::post('ledgers/{ledger}/account-types/reorder', [AccountTypeController::class, 'reorder'])
        ->name('ledgers.account-types.reorder');
    Route::post('ledgers/{ledger}/account-types', [AccountTypeController::class, 'store'])
        ->name('ledgers.account-types.store');
    Route::put('ledgers/{ledger}/account-types/{accountType}', [AccountTypeController::class, 'update'])
        ->name('ledgers.account-types.update');
    Route::delete('ledgers/{ledger}/account-types/{accountType}', [AccountTypeController::class, 'destroy'])
        ->name('ledgers.account-types.destroy');

    // Sample Data
    Route::post('ledgers/{ledger}/sample-data', [SampleDataController::class, 'store'])
        ->name('ledgers.sample-data.store');
    Route::delete('ledgers/{ledger}/sample-data', [SampleDataController::class, 'destroy'])
        ->name('ledgers.sample-data.destroy');
});
