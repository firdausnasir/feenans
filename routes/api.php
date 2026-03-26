<?php

use App\Http\Controllers\Admin\AdminOverviewController;
use App\Http\Controllers\Admin\AdminUserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('overview', AdminOverviewController::class)->name('admin.overview');
    Route::get('users', [AdminUserController::class, 'index'])->name('admin.users.index');
    Route::patch('users/{user}/membership', [AdminUserController::class, 'updateMembership'])->name('admin.users.membership.update');
});
