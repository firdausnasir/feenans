<?php

use App\Http\Controllers\Admin\AdminDashboardPageController;
use App\Http\Controllers\Admin\AdminMembershipPageController;
use App\Http\Controllers\Admin\AdminUserPageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('onboarding/step/{step}', [OnboardingController::class, 'saveStep'])->name('onboarding.step')->where('step', '[12]');
    Route::post('onboarding/autosave', [OnboardingController::class, 'autosave'])->name('onboarding.autosave');
    Route::post('onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
});

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->group(function () {
    Route::get('/', AdminDashboardPageController::class)->name('admin.index');
    Route::get('users', AdminUserPageController::class)->name('admin.users');
    Route::get('memberships', AdminMembershipPageController::class)->name('admin.memberships');
});

require __DIR__.'/ledger.php';
require __DIR__.'/settings.php';
