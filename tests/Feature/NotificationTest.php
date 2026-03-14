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

    $response = $this->actingAs($user)->get(route('notifications.index'));

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

test('markRead marks notification as read', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $bill = Bill::factory()->for($ledger)->for($account)->create();

    $user->notifyNow(new BillDueReminder($bill));
    $notification = $user->notifications()->first();

    $this->actingAs($user)
        ->patch(route('notifications.read', $notification->id))
        ->assertNoContent();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('markAllRead clears all unread notifications', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $bill = Bill::factory()->for($ledger)->for($account)->create();

    $user->notifyNow(new BillDueReminder($bill));
    $user->notifyNow(new BillDueReminder($bill));

    $this->actingAs($user)
        ->patch(route('notifications.read-all'))
        ->assertNoContent();

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});
