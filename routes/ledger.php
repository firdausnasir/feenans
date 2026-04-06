<?php

use App\Http\Controllers\Ledger\AccountController;
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
use App\Http\Controllers\Web\Architecture\ProofTagController as WebProofTagController;
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

    // Premium-gated features
    Route::middleware('premium')->group(function () {
        // Budgets
        Route::get('ledgers/{ledger}/budgets', [BudgetController::class, 'index'])->name('ledgers.budgets.index');
        Route::post('ledgers/{ledger}/budgets', [BudgetController::class, 'store'])->name('ledgers.budgets.store');
        Route::put('ledgers/{ledger}/budgets/{budget}', [BudgetController::class, 'update'])->name('ledgers.budgets.update');
        Route::delete('ledgers/{ledger}/budgets/{budget}', [BudgetController::class, 'destroy'])->name('ledgers.budgets.destroy');

        // Bills
        Route::get('ledgers/{ledger}/bills', [BillController::class, 'index'])
            ->name('ledgers.bills.index');
        Route::get('ledgers/{ledger}/bills/create', [BillController::class, 'create'])
            ->name('ledgers.bills.create');
        Route::get('ledgers/{ledger}/bills/{bill}/edit', [BillController::class, 'edit'])
            ->name('ledgers.bills.edit');

        Route::post('ledgers/{ledger}/bills', [BillController::class, 'store'])
            ->name('ledgers.bills.store');
        Route::put('ledgers/{ledger}/bills/{bill}', [BillController::class, 'update'])
            ->name('ledgers.bills.update');
        Route::delete('ledgers/{ledger}/bills/{bill}', [BillController::class, 'destroy'])
            ->name('ledgers.bills.destroy');
        Route::patch('ledgers/{ledger}/bills/{bill}/toggle', [BillController::class, 'toggle'])
            ->name('ledgers.bills.toggle');

        Route::post('ledgers/{ledger}/bills/{bill}/pay', [BillController::class, 'pay'])
            ->name('ledgers.bills.pay');

        // Reports
        Route::get('ledgers/{ledger}/reports', [ReportController::class, 'index'])
            ->name('ledgers.reports.index');
        Route::get('ledgers/{ledger}/reports/financial-health', [ReportController::class, 'financialHealth'])
            ->name('ledgers.reports.financial-health');
        Route::get('ledgers/{ledger}/reports/budget-performance', [ReportController::class, 'budgetPerformance'])
            ->name('ledgers.reports.budget-performance');
        Route::get('ledgers/{ledger}/reports/cash-flow', [ReportController::class, 'cashFlow'])
            ->name('ledgers.reports.cash-flow');
        Route::get('ledgers/{ledger}/reports/export-pdf', [ReportController::class, 'exportPdf'])
            ->name('ledgers.reports.export-pdf');
    });

    // Import
    Route::get('ledgers/{ledger}/import', [ImportController::class, 'create'])->name('ledgers.import.create');
    Route::post('ledgers/{ledger}/import/parse', [ImportController::class, 'parse'])->name('ledgers.import.parse');
    Route::post('ledgers/{ledger}/import/execute', [ImportController::class, 'execute'])->name('ledgers.import.execute');
    Route::post('ledgers/{ledger}/import/mappings', [ImportController::class, 'storeMapping'])->name('ledgers.import.mappings.store');
    Route::delete('ledgers/{ledger}/import/mappings/{importMapping}', [ImportController::class, 'destroyMapping'])->name('ledgers.import.mappings.destroy');

    // Transactions
    Route::get('ledgers/{ledger}/transactions/export', [TransactionController::class, 'export'])
        ->name('ledgers.transactions.export');
    Route::get('ledgers/{ledger}/transactions', [TransactionController::class, 'index'])
        ->name('ledgers.transactions.index');
    Route::post('ledgers/{ledger}/transactions', [TransactionController::class, 'store'])
        ->name('ledgers.transactions.store');
    Route::get('ledgers/{ledger}/transactions/{transaction}/edit', [TransactionController::class, 'edit'])
        ->name('ledgers.transactions.edit');
    Route::get('ledgers/{ledger}/transactions/{transaction}/attachments', [AttachmentController::class, 'index'])
        ->name('ledgers.transactions.attachments.index');
    Route::post('ledgers/{ledger}/transactions/{transaction}/attachments', [AttachmentController::class, 'store'])
        ->name('ledgers.transactions.attachments.store');
    Route::get('ledgers/{ledger}/transactions/{transaction}/attachments/{attachment}', [AttachmentController::class, 'show'])
        ->name('ledgers.transactions.attachments.show');
    Route::delete('ledgers/{ledger}/transactions/{transaction}/attachments/{attachment}', [AttachmentController::class, 'destroy'])
        ->name('ledgers.transactions.attachments.destroy');
    Route::put('ledgers/{ledger}/transactions/{transaction}', [TransactionController::class, 'update'])
        ->name('ledgers.transactions.update');
    Route::delete('ledgers/{ledger}/transactions/{transaction}', [TransactionController::class, 'destroy'])
        ->name('ledgers.transactions.destroy');
    Route::post('ledgers/{ledger}/transactions/bulk-update', [TransactionController::class, 'bulkUpdate'])
        ->name('ledgers.transactions.bulk-update');
    Route::post('ledgers/{ledger}/transactions/bulk-destroy', [TransactionController::class, 'bulkDestroy'])
        ->name('ledgers.transactions.bulk-destroy');
    Route::post('ledgers/{ledger}/transactions/select-all', [TransactionController::class, 'selectAll'])
        ->name('ledgers.transactions.select-all');

    // Accounts
    Route::get('ledgers/{ledger}/accounts', [AccountController::class, 'index'])
        ->name('ledgers.accounts.index');
    Route::get('ledgers/{ledger}/accounts/{account}/export', [AccountController::class, 'export'])
        ->name('ledgers.accounts.export');
    Route::post('ledgers/{ledger}/accounts', [AccountController::class, 'store'])
        ->name('ledgers.accounts.store');
    Route::put('ledgers/{ledger}/accounts/{account}', [AccountController::class, 'update'])
        ->name('ledgers.accounts.update');
    Route::delete('ledgers/{ledger}/accounts/{account}', [AccountController::class, 'destroy'])
        ->name('ledgers.accounts.destroy');
    Route::post('ledgers/{ledger}/accounts/reorder', [AccountController::class, 'reorder'])
        ->name('ledgers.accounts.reorder');
    Route::post('ledgers/{ledger}/accounts/{account}/adjust-balance', [AccountController::class, 'adjustBalance'])
        ->name('ledgers.accounts.adjust-balance');

    // Categories
    Route::get('ledgers/{ledger}/categories', [CategoryController::class, 'index'])
        ->name('ledgers.categories.index');
    Route::post('ledgers/{ledger}/categories', [CategoryController::class, 'store'])
        ->name('ledgers.categories.store');
    Route::patch('ledgers/{ledger}/categories/{category}', [CategoryController::class, 'update'])
        ->name('ledgers.categories.update');
    Route::delete('ledgers/{ledger}/categories/{category}', [CategoryController::class, 'destroy'])
        ->name('ledgers.categories.destroy');
    Route::post('ledgers/{ledger}/categories/reorder', [CategoryController::class, 'reorder'])
        ->name('ledgers.categories.reorder');

    // Payees
    Route::get('ledgers/{ledger}/payees', [PayeeController::class, 'index'])
        ->name('ledgers.payees.index');
    Route::post('ledgers/{ledger}/payees', [PayeeController::class, 'store'])
        ->name('ledgers.payees.store');
    Route::patch('ledgers/{ledger}/payees/{payee}', [PayeeController::class, 'update'])
        ->name('ledgers.payees.update');
    Route::delete('ledgers/{ledger}/payees/{payee}', [PayeeController::class, 'destroy'])
        ->name('ledgers.payees.destroy');

    // Tags
    Route::get('ledgers/{ledger}/tags', [TagController::class, 'index'])->name('ledgers.tags.index');
    Route::post('ledgers/{ledger}/tags', [TagController::class, 'store'])->name('ledgers.tags.store');
    Route::patch('ledgers/{ledger}/tags/{tag}', [TagController::class, 'update'])->name('ledgers.tags.update');
    Route::delete('ledgers/{ledger}/tags/{tag}', [TagController::class, 'destroy'])->name('ledgers.tags.destroy');

    // Temporary architecture proof routes for shared request pipeline rollout.
    Route::get('ledgers/{ledger}/architecture/proof-tags', [WebProofTagController::class, 'index'])
        ->name('architecture.proof-tags.index');
    Route::post('ledgers/{ledger}/architecture/proof-tags', [WebProofTagController::class, 'store'])
        ->name('architecture.proof-tags.store');

    // Activity
    Route::get('ledgers/{ledger}/activity', [ActivityLogController::class, 'index'])
        ->name('ledgers.activity.index');

    // Settings
    Route::get('ledgers/{ledger}/settings', [SettingsController::class, 'index'])
        ->name('ledgers.settings.index');
    Route::put('ledgers/{ledger}/settings', [SettingsController::class, 'update'])
        ->name('ledgers.settings.update');
    Route::post('ledgers/{ledger}/settings/account-types', [SettingsController::class, 'storeAccountType'])
        ->name('ledgers.settings.account-types.store');
    Route::put('ledgers/{ledger}/settings/account-types/{accountType}', [SettingsController::class, 'updateAccountType'])
        ->name('ledgers.settings.account-types.update');
    Route::delete('ledgers/{ledger}/settings/account-types/{accountType}', [SettingsController::class, 'destroyAccountType'])
        ->name('ledgers.settings.account-types.destroy');
    Route::post('ledgers/{ledger}/settings/account-types/reorder', [SettingsController::class, 'reorderAccountTypes'])
        ->name('ledgers.settings.account-types.reorder');

    // Data Export
    Route::get('ledgers/{ledger}/export', DataExportController::class)
        ->name('ledgers.export');

    // Sample Data
    Route::post('ledgers/{ledger}/sample-data', [SampleDataController::class, 'store'])
        ->name('ledgers.sample-data.store');
    Route::delete('ledgers/{ledger}/sample-data', [SampleDataController::class, 'destroy'])
        ->name('ledgers.sample-data.destroy');
});
