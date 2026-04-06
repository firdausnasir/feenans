<?php

use App\Http\Controllers\Admin\AdminFeedbackController;
use App\Http\Controllers\Admin\AdminMembershipController;
use App\Http\Controllers\Admin\AdminOverviewController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Architecture\ProofTagController as ApiProofTagController;
use App\Http\Controllers\Api\V1\Auth\ApiTokenController;
use App\Http\Controllers\Api\V1\Ledger\AccountController as ApiAccountController;
use App\Http\Controllers\Api\V1\Ledger\ActivityLogController as ApiActivityLogController;
use App\Http\Controllers\Api\V1\Ledger\BillController as ApiBillController;
use App\Http\Controllers\Api\V1\Ledger\BudgetController as ApiBudgetController;
use App\Http\Controllers\Api\V1\Ledger\CategoryController as ApiCategoryController;
use App\Http\Controllers\Api\V1\Ledger\ImportController as ApiImportController;
use App\Http\Controllers\Api\V1\Ledger\PayeeController as ApiPayeeController;
use App\Http\Controllers\Api\V1\Ledger\ReportController as ApiReportController;
use App\Http\Controllers\Api\V1\Ledger\TagController as ApiTagController;
use App\Http\Controllers\Api\V1\Ledger\TransactionAttachmentController as ApiTransactionAttachmentController;
use App\Http\Controllers\Api\V1\Ledger\TransactionController as ApiTransactionController;
use App\Http\Controllers\Api\V1\LedgerController as ApiLedgerController;
use App\Http\Controllers\Api\V1\Settings\SecuritySettingsController as ApiSecuritySettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('overview', AdminOverviewController::class)->name('admin.overview');
    Route::get('users', [AdminUserController::class, 'index'])->name('admin.users.index');
    Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
    Route::get('memberships', [AdminMembershipController::class, 'index'])->name('admin.memberships.index');
    Route::patch('users/{user}/membership', [AdminMembershipController::class, 'update'])->name('admin.memberships.update');
    Route::get('feedbacks', [AdminFeedbackController::class, 'index'])->name('admin.feedbacks.index');
});

Route::middleware(['auth', 'verified'])->prefix('v1')->as('api.v1.')->scopeBindings()->group(function () {
    Route::get('ledgers/{ledger}/architecture/proof-tags/exception', [ApiProofTagController::class, 'exception'])
        ->name('architecture.proof-tags.exception');
    Route::post('ledgers/{ledger}/architecture/proof-tags', [ApiProofTagController::class, 'store'])
        ->name('architecture.proof-tags.store');
    Route::post('auth/tokens', [ApiTokenController::class, 'store'])
        ->name('auth.tokens.store');

    Route::get('settings/security', ApiSecuritySettingsController::class)
        ->name('settings.security');
});

Route::middleware(['auth:sanctum', 'verified'])->prefix('v1')->as('api.v1.')->scopeBindings()->group(function () {
    Route::delete('auth/tokens/current', [ApiTokenController::class, 'destroyCurrent'])
        ->name('auth.tokens.current.destroy');
    Route::delete('auth/tokens/{token}', [ApiTokenController::class, 'destroy'])
        ->whereNumber('token')
        ->name('auth.tokens.destroy');

    Route::get('ledgers', [ApiLedgerController::class, 'index'])
        ->name('ledgers.index');
    Route::get('ledgers/{ledger}', [ApiLedgerController::class, 'show'])
        ->name('ledgers.show');
    Route::get('ledgers/{ledger}/has-sample-data', [ApiLedgerController::class, 'hasSampleData'])
        ->name('ledgers.has-sample-data');

    Route::get('ledgers/{ledger}/accounts', [ApiAccountController::class, 'index'])
        ->name('ledgers.accounts.index');
    Route::get('ledgers/{ledger}/accounts/grouped', [ApiAccountController::class, 'grouped'])
        ->name('ledgers.accounts.grouped');
    Route::get('ledgers/{ledger}/accounts/types', [ApiAccountController::class, 'types'])
        ->name('ledgers.accounts.types');
    Route::get('ledgers/{ledger}/accounts/net-worth', [ApiAccountController::class, 'netWorth'])
        ->name('ledgers.accounts.net-worth');
    Route::get('ledgers/{ledger}/activity', [ApiActivityLogController::class, 'index'])
        ->name('ledgers.activity.index');
    Route::post('ledgers/{ledger}/accounts', [ApiAccountController::class, 'store'])
        ->name('ledgers.accounts.store');
    Route::patch('ledgers/{ledger}/accounts/{account}', [ApiAccountController::class, 'update'])
        ->name('ledgers.accounts.update');
    Route::delete('ledgers/{ledger}/accounts/{account}', [ApiAccountController::class, 'destroy'])
        ->name('ledgers.accounts.destroy');
    Route::post('ledgers/{ledger}/accounts/reorder', [ApiAccountController::class, 'reorder'])
        ->name('ledgers.accounts.reorder');
    Route::post('ledgers/{ledger}/accounts/{account}/adjust-balance', [ApiAccountController::class, 'adjustBalance'])
        ->name('ledgers.accounts.adjust-balance');

    Route::get('ledgers/{ledger}/tags', [ApiTagController::class, 'index'])
        ->name('ledgers.tags.index');
    Route::post('ledgers/{ledger}/tags', [ApiTagController::class, 'store'])
        ->name('ledgers.tags.store');
    Route::patch('ledgers/{ledger}/tags/{tag}', [ApiTagController::class, 'update'])
        ->name('ledgers.tags.update');
    Route::delete('ledgers/{ledger}/tags/{tag}', [ApiTagController::class, 'destroy'])
        ->name('ledgers.tags.destroy');

    Route::get('ledgers/{ledger}/categories', [ApiCategoryController::class, 'index'])
        ->name('ledgers.categories.index');
    Route::get('ledgers/{ledger}/categories/dashboard-top', [ApiCategoryController::class, 'dashboardTop'])
        ->name('ledgers.categories.dashboard-top');
    Route::get('ledgers/{ledger}/categories/dashboard-uncategorized-count', [ApiCategoryController::class, 'dashboardUncategorizedCount'])
        ->name('ledgers.categories.dashboard-uncategorized-count');
    Route::post('ledgers/{ledger}/categories', [ApiCategoryController::class, 'store'])
        ->name('ledgers.categories.store');
    Route::patch('ledgers/{ledger}/categories/{category}', [ApiCategoryController::class, 'update'])
        ->name('ledgers.categories.update');
    Route::delete('ledgers/{ledger}/categories/{category}', [ApiCategoryController::class, 'destroy'])
        ->name('ledgers.categories.destroy');
    Route::post('ledgers/{ledger}/categories/reorder', [ApiCategoryController::class, 'reorder'])
        ->name('ledgers.categories.reorder');

    Route::get('ledgers/{ledger}/payees', [ApiPayeeController::class, 'index'])
        ->name('ledgers.payees.index');
    Route::post('ledgers/{ledger}/payees', [ApiPayeeController::class, 'store'])
        ->name('ledgers.payees.store');
    Route::patch('ledgers/{ledger}/payees/{payee}', [ApiPayeeController::class, 'update'])
        ->name('ledgers.payees.update');
    Route::delete('ledgers/{ledger}/payees/{payee}', [ApiPayeeController::class, 'destroy'])
        ->name('ledgers.payees.destroy');

    Route::get('ledgers/{ledger}/import/accounts', [ApiImportController::class, 'accounts'])
        ->name('ledgers.import.accounts');
    Route::get('ledgers/{ledger}/import/saved-mappings', [ApiImportController::class, 'savedMappings'])
        ->name('ledgers.import.saved-mappings');
    Route::get('ledgers/{ledger}/import/history', [ApiImportController::class, 'history'])
        ->name('ledgers.import.history');
    Route::post('ledgers/{ledger}/import/parse', [ApiImportController::class, 'parse'])
        ->name('ledgers.import.parse');
    Route::post('ledgers/{ledger}/import/execute', [ApiImportController::class, 'execute'])
        ->name('ledgers.import.execute');
    Route::post('ledgers/{ledger}/import/mappings', [ApiImportController::class, 'store'])
        ->name('ledgers.import.mappings.store');
    Route::delete('ledgers/{ledger}/import/mappings/{importMapping}', [ApiImportController::class, 'destroy'])
        ->name('ledgers.import.mappings.destroy');

    Route::get('ledgers/{ledger}/transactions', [ApiTransactionController::class, 'index'])
        ->name('ledgers.transactions.index');
    Route::get('ledgers/{ledger}/transactions/dashboard-summary', [ApiTransactionController::class, 'dashboardSummary'])
        ->name('ledgers.transactions.dashboard-summary');
    Route::get('ledgers/{ledger}/transactions/dashboard-daily-trend', [ApiTransactionController::class, 'dashboardDailyTrend'])
        ->name('ledgers.transactions.dashboard-daily-trend');
    Route::get('ledgers/{ledger}/transactions/dashboard-recent', [ApiTransactionController::class, 'dashboardRecent'])
        ->name('ledgers.transactions.dashboard-recent');
    Route::post('ledgers/{ledger}/transactions', [ApiTransactionController::class, 'store'])
        ->name('ledgers.transactions.store');
    Route::post('ledgers/{ledger}/transactions/bulk-update', [ApiTransactionController::class, 'bulkUpdate'])
        ->name('ledgers.transactions.bulk-update');
    Route::post('ledgers/{ledger}/transactions/bulk-destroy', [ApiTransactionController::class, 'bulkDestroy'])
        ->name('ledgers.transactions.bulk-destroy');
    Route::post('ledgers/{ledger}/transactions/select-all', [ApiTransactionController::class, 'selectAll'])
        ->name('ledgers.transactions.select-all');
    Route::get('ledgers/{ledger}/transactions/{transaction}', [ApiTransactionController::class, 'show'])
        ->name('ledgers.transactions.show');
    Route::patch('ledgers/{ledger}/transactions/{transaction}', [ApiTransactionController::class, 'update'])
        ->name('ledgers.transactions.update');
    Route::delete('ledgers/{ledger}/transactions/{transaction}', [ApiTransactionController::class, 'destroy'])
        ->name('ledgers.transactions.destroy');
    Route::get('ledgers/{ledger}/transactions/{transaction}/attachments', [ApiTransactionAttachmentController::class, 'index'])
        ->name('ledgers.transactions.attachments.index');
    Route::post('ledgers/{ledger}/transactions/{transaction}/attachments', [ApiTransactionAttachmentController::class, 'store'])
        ->name('ledgers.transactions.attachments.store');
    Route::delete('ledgers/{ledger}/transactions/{transaction}/attachments/{attachment}', [ApiTransactionAttachmentController::class, 'destroy'])
        ->name('ledgers.transactions.attachments.destroy');

    Route::middleware('premium')->group(function () {
        Route::get('ledgers/{ledger}/bills', [ApiBillController::class, 'index'])
            ->name('ledgers.bills.index');
        Route::get('ledgers/{ledger}/bills/dashboard-upcoming', [ApiBillController::class, 'dashboardUpcoming'])
            ->name('ledgers.bills.dashboard-upcoming');
        Route::post('ledgers/{ledger}/bills', [ApiBillController::class, 'store'])
            ->name('ledgers.bills.store');
        Route::get('ledgers/{ledger}/bills/{bill}', [ApiBillController::class, 'show'])
            ->name('ledgers.bills.show');
        Route::patch('ledgers/{ledger}/bills/{bill}', [ApiBillController::class, 'update'])
            ->name('ledgers.bills.update');
        Route::delete('ledgers/{ledger}/bills/{bill}', [ApiBillController::class, 'destroy'])
            ->name('ledgers.bills.destroy');
        Route::patch('ledgers/{ledger}/bills/{bill}/toggle', [ApiBillController::class, 'toggle'])
            ->name('ledgers.bills.toggle');
        Route::post('ledgers/{ledger}/bills/{bill}/pay', [ApiBillController::class, 'pay'])
            ->name('ledgers.bills.pay');

        Route::get('ledgers/{ledger}/budgets', [ApiBudgetController::class, 'index'])
            ->name('ledgers.budgets.index');
        Route::get('ledgers/{ledger}/budgets/dashboard-top', [ApiBudgetController::class, 'dashboardTop'])
            ->name('ledgers.budgets.dashboard-top');
        Route::post('ledgers/{ledger}/budgets', [ApiBudgetController::class, 'store'])
            ->name('ledgers.budgets.store');
        Route::patch('ledgers/{ledger}/budgets/{budget}', [ApiBudgetController::class, 'update'])
            ->name('ledgers.budgets.update');
        Route::delete('ledgers/{ledger}/budgets/{budget}', [ApiBudgetController::class, 'destroy'])
            ->name('ledgers.budgets.destroy');

        Route::get('ledgers/{ledger}/reports', [ApiReportController::class, 'index'])
            ->name('ledgers.reports.index');
        Route::get('ledgers/{ledger}/reports/financial-health', [ApiReportController::class, 'financialHealth'])
            ->name('ledgers.reports.financial-health');
        Route::get('ledgers/{ledger}/reports/budget-performance', [ApiReportController::class, 'budgetPerformance'])
            ->name('ledgers.reports.budget-performance');
        Route::get('ledgers/{ledger}/reports/cash-flow', [ApiReportController::class, 'cashFlow'])
            ->name('ledgers.reports.cash-flow');
    });
});
