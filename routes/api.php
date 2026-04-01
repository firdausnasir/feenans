<?php

use App\Http\Controllers\Admin\AdminMembershipController;
use App\Http\Controllers\Admin\AdminOverviewController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Architecture\ProofTagController as ApiProofTagController;
use App\Http\Controllers\Api\V1\Auth\ApiTokenController;
use App\Http\Controllers\Api\V1\Ledger\AccountController as ApiAccountController;
use App\Http\Controllers\Api\V1\Ledger\BudgetController as ApiBudgetController;
use App\Http\Controllers\Api\V1\Ledger\CategoryController as ApiCategoryController;
use App\Http\Controllers\Api\V1\Ledger\PayeeController as ApiPayeeController;
use App\Http\Controllers\Api\V1\Ledger\TagController as ApiTagController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('overview', AdminOverviewController::class)->name('admin.overview');
    Route::get('users', [AdminUserController::class, 'index'])->name('admin.users.index');
    Route::get('memberships', [AdminMembershipController::class, 'index'])->name('admin.memberships.index');
    Route::patch('users/{user}/membership', [AdminMembershipController::class, 'update'])->name('admin.memberships.update');
});

Route::middleware(['auth', 'verified'])->prefix('v1')->as('api.v1.')->scopeBindings()->group(function () {
    Route::get('ledgers/{ledger}/architecture/proof-tags/exception', [ApiProofTagController::class, 'exception'])
        ->name('architecture.proof-tags.exception');
    Route::post('ledgers/{ledger}/architecture/proof-tags', [ApiProofTagController::class, 'store'])
        ->name('architecture.proof-tags.store');
    Route::post('auth/tokens', [ApiTokenController::class, 'store'])
        ->name('auth.tokens.store');
});

Route::middleware(['auth:sanctum', 'verified'])->prefix('v1')->as('api.v1.')->scopeBindings()->group(function () {
    Route::delete('auth/tokens/current', [ApiTokenController::class, 'destroyCurrent'])
        ->name('auth.tokens.current.destroy');
    Route::delete('auth/tokens/{token}', [ApiTokenController::class, 'destroy'])
        ->whereNumber('token')
        ->name('auth.tokens.destroy');

    Route::get('ledgers/{ledger}/accounts', [ApiAccountController::class, 'index'])
        ->name('ledgers.accounts.index');
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

    Route::middleware('premium')->group(function () {
        Route::get('ledgers/{ledger}/budgets', [ApiBudgetController::class, 'index'])
            ->name('ledgers.budgets.index');
        Route::post('ledgers/{ledger}/budgets', [ApiBudgetController::class, 'store'])
            ->name('ledgers.budgets.store');
        Route::patch('ledgers/{ledger}/budgets/{budget}', [ApiBudgetController::class, 'update'])
            ->name('ledgers.budgets.update');
        Route::delete('ledgers/{ledger}/budgets/{budget}', [ApiBudgetController::class, 'destroy'])
            ->name('ledgers.budgets.destroy');
    });
});
