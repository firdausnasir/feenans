<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bill;
use App\Models\Ledger;
use App\Models\User;
use App\Notifications\BillDueReminder;
use App\Notifications\BillOverdue;
use Inertia\Testing\AssertableInertia as Assert;

test('index returns unread notifications for authenticated user', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $bill = Bill::factory()->for($ledger)->for($account)->create();

    $user->notifyNow(new BillDueReminder(collect(), collect([$bill]), collect()));
    $user->notifyNow(new BillDueReminder(collect(), collect([$bill]), collect()));
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

    $user->notifyNow(new BillDueReminder(collect(), collect([$bill]), collect()));
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

    $user->notifyNow(new BillDueReminder(collect(), collect([$bill]), collect()));
    $user->notifyNow(new BillDueReminder(collect(), collect([$bill]), collect()));

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

    $user->notifyNow(new BillDueReminder(collect(), collect([$bill]), collect()));
    $notification = $user->notifications()->first();

    $this->actingAs($user)
        ->from(route('ledgers.index'))
        ->delete(route('notifications.destroy', $notification->id))
        ->assertRedirect(route('ledgers.index'));

    expect($user->fresh()->notifications()->count())->toBe(0);
});

test('notifications prop returns readable copy for bill summary reminders', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $bill = Bill::factory()->for($ledger)->for($account)->create([
        'name' => 'Internet',
    ]);

    $user->notifyNow(new BillDueReminder(collect(), collect([$bill]), collect()));

    $this->actingAs($user)
        ->get(route('ledgers.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->reloadOnly('notifications', fn (Assert $reload) => $reload
                ->where('notifications.data.0.data.type', 'bill_summary_reminder')
                ->where('notifications.data.0.data.due_today_count', 1)
            )
        );
});

test('bill due reminder mail uses the bill ledger currency', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create([
        'currency_code' => 'EUR',
    ]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $bill = Bill::factory()->for($ledger)->for($account)->create([
        'name' => 'Internet',
        'amount' => 79.99,
    ]);

    $message = (new BillDueReminder(collect(), collect([$bill]), collect()))->toMail($user);

    expect($message->introLines)->toContain('• Internet - Amount: €79.99');
});

test('bill overdue mail uses the bill ledger currency', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create([
        'currency_code' => 'EUR',
    ]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $bill = Bill::factory()->for($ledger)->for($account)->create([
        'name' => 'Electric',
        'amount' => 120.50,
        'next_due_date' => now()->subDay(),
    ]);

    $message = (new BillOverdue($bill))->toMail($user);

    expect($message->introLines)->toContain('Amount: €120.50');
});
