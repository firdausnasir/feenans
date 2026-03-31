<?php

use App\Http\Controllers\Admin\AdminMembershipController;
use App\Http\Controllers\Admin\AdminOverviewController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Architecture\ProofTagController as ApiProofTagController;
use App\Http\Controllers\Api\V1\Auth\ApiTokenController;
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

    Route::get('ledgers/{ledger}/tags', [ApiTagController::class, 'index'])
        ->name('ledgers.tags.index');
    Route::post('ledgers/{ledger}/tags', [ApiTagController::class, 'store'])
        ->name('ledgers.tags.store');
    Route::patch('ledgers/{ledger}/tags/{tag}', [ApiTagController::class, 'update'])
        ->name('ledgers.tags.update');
    Route::delete('ledgers/{ledger}/tags/{tag}', [ApiTagController::class, 'destroy'])
        ->name('ledgers.tags.destroy');
});
