<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AccountTypeController;
use App\Http\Controllers\Api\V1\ActivityLogController;
use App\Http\Controllers\Api\V1\ApiTokenController;
use App\Http\Controllers\Api\V1\BillController;
use App\Http\Controllers\Api\V1\BudgetController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\LedgerCycleController;
use App\Http\Controllers\Api\V1\PayeeController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('v1')->name('api.v1.')->group(function () {
    Route::scopeBindings()->group(function () {
        // Dashboard / summary endpoints (before resource to avoid {transaction} conflicts)
        Route::get('ledgers/{ledger}/transactions/summary', [TransactionController::class, 'summary'])->name('ledgers.transactions.summary');
        Route::get('ledgers/{ledger}/transactions/daily-trend', [TransactionController::class, 'dailyTrend'])->name('ledgers.transactions.daily-trend');
        Route::get('ledgers/{ledger}/transactions/uncategorized-count', [TransactionController::class, 'uncategorizedCount'])->name('ledgers.transactions.uncategorized-count');
        Route::get('ledgers/{ledger}/categories/top-spending', [CategoryController::class, 'topSpending'])->name('ledgers.categories.top-spending');
        Route::get('ledgers/{ledger}/cycle', [LedgerCycleController::class, 'show'])->name('ledgers.cycle');

        // Transaction-specific routes (before resource to avoid {transaction} conflicts)
        Route::post('ledgers/{ledger}/transactions/bulk-update', [TransactionController::class, 'bulkUpdate'])->name('ledgers.transactions.bulk-update');
        Route::post('ledgers/{ledger}/transactions/bulk-destroy', [TransactionController::class, 'bulkDestroy'])->name('ledgers.transactions.bulk-destroy');
        Route::post('ledgers/{ledger}/transactions/select-all', [TransactionController::class, 'selectAll'])->name('ledgers.transactions.select-all');
        Route::get('ledgers/{ledger}/transactions/export', [TransactionController::class, 'export'])->name('ledgers.transactions.export');

        // Transaction attachment routes
        Route::post('ledgers/{ledger}/transactions/{transaction}/attachments', [TransactionController::class, 'storeAttachment'])->name('ledgers.transactions.attachments.store');
        Route::get('ledgers/{ledger}/transactions/{transaction}/attachments/{attachment}', [TransactionController::class, 'showAttachment'])->name('ledgers.transactions.attachments.show');
        Route::delete('ledgers/{ledger}/transactions/{transaction}/attachments/{attachment}', [TransactionController::class, 'destroyAttachment'])->name('ledgers.transactions.attachments.destroy');

        Route::apiResource('ledgers.transactions', TransactionController::class);
        // Account action routes (before resource to avoid {account} conflicts)
        Route::patch('ledgers/{ledger}/accounts/{account}/toggle-visibility', [AccountController::class, 'toggleVisibility'])->name('ledgers.accounts.toggle-visibility');
        Route::post('ledgers/{ledger}/accounts/{account}/adjust-balance', [AccountController::class, 'adjustBalance'])->name('ledgers.accounts.adjust-balance');
        Route::post('ledgers/{ledger}/accounts/reorder', [AccountController::class, 'reorder'])->name('ledgers.accounts.reorder');
        Route::get('ledgers/{ledger}/accounts/{account}/export', [AccountController::class, 'export'])->name('ledgers.accounts.export');
        Route::get('ledgers/{ledger}/accounts/{account}/transactions', [AccountController::class, 'transactions'])->name('ledgers.accounts.transactions');
        Route::get('ledgers/{ledger}/accounts/{account}/statement', [AccountController::class, 'statement'])->name('ledgers.accounts.statement');
        Route::get('ledgers/{ledger}/accounts/{account}/monthly-balances', [AccountController::class, 'monthlyBalances'])->name('ledgers.accounts.monthly-balances');

        // Net worth endpoint
        Route::get('ledgers/{ledger}/net-worth', [AccountController::class, 'netWorth'])->name('ledgers.net-worth');

        // Account types
        Route::post('ledgers/{ledger}/account-types/reorder', [AccountTypeController::class, 'reorder'])->name('ledgers.account-types.reorder');
        Route::apiResource('ledgers.account-types', AccountTypeController::class);

        Route::apiResource('ledgers.accounts', AccountController::class);
        // Categories: full CRUD + reorder
        Route::post('ledgers/{ledger}/categories/reorder', [CategoryController::class, 'reorder'])->name('ledgers.categories.reorder');
        Route::apiResource('ledgers.categories', CategoryController::class);

        // Payees: full CRUD + merge
        Route::post('ledgers/{ledger}/payees/merge', [PayeeController::class, 'merge'])->name('ledgers.payees.merge');
        Route::apiResource('ledgers.payees', PayeeController::class);

        Route::post('ledgers/{ledger}/bills/{bill}/pay', [BillController::class, 'pay'])->name('ledgers.bills.pay');
        Route::patch('ledgers/{ledger}/bills/{bill}/toggle', [BillController::class, 'toggle'])->name('ledgers.bills.toggle');
        Route::apiResource('ledgers.bills', BillController::class);

        // Budgets: full CRUD
        Route::apiResource('ledgers.budgets', BudgetController::class);

        // Tags: full CRUD
        Route::apiResource('ledgers.tags', TagController::class);

        // Settings
        Route::get('ledgers/{ledger}/settings', [SettingsController::class, 'index'])->name('ledgers.settings.index');
        Route::put('ledgers/{ledger}/settings', [SettingsController::class, 'update'])->name('ledgers.settings.update');

        // Sample data
        Route::post('ledgers/{ledger}/sample-data', [SettingsController::class, 'generateSampleData'])->name('ledgers.sample-data.store');
        Route::delete('ledgers/{ledger}/sample-data', [SettingsController::class, 'removeSampleData'])->name('ledgers.sample-data.destroy');

        // Import
        Route::get('ledgers/{ledger}/import/history', [ImportController::class, 'history'])->name('ledgers.import.history');
        Route::post('ledgers/{ledger}/import/parse', [ImportController::class, 'parse'])->name('ledgers.import.parse');
        Route::post('ledgers/{ledger}/import/execute', [ImportController::class, 'execute'])->name('ledgers.import.execute');
        Route::get('ledgers/{ledger}/import/mappings', [ImportController::class, 'mappings'])->name('ledgers.import.mappings');
        Route::post('ledgers/{ledger}/import/mappings', [ImportController::class, 'storeMapping'])->name('ledgers.import.mappings.store');
        Route::delete('ledgers/{ledger}/import/mappings/{importMapping}', [ImportController::class, 'destroyMapping'])->name('ledgers.import.mappings.destroy');

        // Reports
        Route::prefix('ledgers/{ledger}/reports')->name('ledgers.reports.')->group(function () {
            Route::get('spending', [ReportController::class, 'spending'])->name('spending');
            Route::get('cash-flow', [ReportController::class, 'cashFlow'])->name('cash-flow');
            Route::get('budget-performance', [ReportController::class, 'budgetPerformance'])->name('budget-performance');
            Route::get('financial-health', [ReportController::class, 'financialHealth'])->name('financial-health');
        });

        // Activity
        Route::get('ledgers/{ledger}/activity', [ActivityLogController::class, 'index'])->name('ledgers.activity.index');
    });

    Route::get('tokens', [ApiTokenController::class, 'index'])->name('tokens.index');
    Route::post('tokens', [ApiTokenController::class, 'store'])->name('tokens.store');
    Route::delete('tokens/{token}', [ApiTokenController::class, 'destroy'])->name('tokens.destroy');
});
