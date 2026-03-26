<?php

use App\Http\Controllers\Admin\AdminMembershipController;
use App\Http\Controllers\Admin\AdminOverviewController;
use App\Http\Controllers\Admin\AdminUserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('overview', AdminOverviewController::class)->name('admin.overview');
    Route::get('users', [AdminUserController::class, 'index'])->name('admin.users.index');
    Route::get('memberships', [AdminMembershipController::class, 'index'])->name('admin.memberships.index');
    Route::patch('users/{user}/membership', [AdminMembershipController::class, 'update'])->name('admin.memberships.update');
});
