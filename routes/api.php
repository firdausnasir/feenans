<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\ApiTokenController;
use App\Http\Controllers\Api\V1\BillController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\PayeeController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('v1')->name('api.v1.')->group(function () {
    Route::scopeBindings()->group(function () {
        Route::apiResource('ledgers.transactions', TransactionController::class);
        Route::apiResource('ledgers.accounts', AccountController::class);
        Route::apiResource('ledgers.categories', CategoryController::class)->only(['index', 'show']);
        Route::apiResource('ledgers.payees', PayeeController::class)->only(['index', 'show']);
        Route::apiResource('ledgers.bills', BillController::class)->only(['index', 'show']);
        Route::apiResource('ledgers.tags', TagController::class)->only(['index', 'show']);
    });

    Route::get('tokens', [ApiTokenController::class, 'index'])->name('tokens.index');
    Route::post('tokens', [ApiTokenController::class, 'store'])->name('tokens.store');
    Route::delete('tokens/{token}', [ApiTokenController::class, 'destroy'])->name('tokens.destroy');
});
