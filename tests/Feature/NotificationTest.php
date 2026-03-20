<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bill;
use App\Models\Ledger;
use App\Models\User;
use App\Notifications\BillDueReminder;

test('index returns unread notifications for authenticated user', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $bill = Bill::factory()->for($ledger)->for($account)->create();

    $user->notifyNow(new BillDueReminder($bill));
    $user->notifyNow(new BillDueReminder($bill));
    $user->notifications()->latest()->first()->markAsRead();

    $response = $this->actingAs($user)->get(route('ledgers.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('unread_notifications_count', 1)
    );
});

test('markRead marks notification as read through the web flow', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $bill = Bill::factory()->for($ledger)->for($account)->create();

    $user->notifyNow(new BillDueReminder($bill));
    $notification = $user->notifications()->first();

    $this->actingAs($user)
        ->from(route('ledgers.index'))
        ->patch(route('notifications.read', $notification->id))
        ->assertRedirect(route('ledgers.index'));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('markAllRead clears all unread notifications through the web flow', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $bill = Bill::factory()->for($ledger)->for($account)->create();

    $user->notifyNow(new BillDueReminder($bill));
    $user->notifyNow(new BillDueReminder($bill));

    $this->actingAs($user)
        ->from(route('ledgers.index'))
        ->patch(route('notifications.read-all'))
        ->assertRedirect(route('ledgers.index'));

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});

test('destroy removes the notification through the web flow', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $bill = Bill::factory()->for($ledger)->for($account)->create();

    $user->notifyNow(new BillDueReminder($bill));
    $notification = $user->notifications()->first();

    $this->actingAs($user)
        ->from(route('ledgers.index'))
        ->delete(route('notifications.destroy', $notification->id))
        ->assertRedirect(route('ledgers.index'));

    expect($user->fresh()->notifications()->count())->toBe(0);
});
