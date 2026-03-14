<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bill;
use App\Models\Ledger;
use App\Models\User;
use Carbon\CarbonImmutable;

test('check-reminders sends notifications for bills due within 3 days', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 15));

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    Bill::factory()->for($ledger)->for($account)->create([
        'next_due_date' => CarbonImmutable::create(2026, 3, 17),
        'is_active' => true,
    ]);

    $this->artisan('bills:check-reminders')->assertSuccessful();

    expect($user->notifications()->count())->toBe(1);
    expect($user->notifications()->first()->data['type'])->toBe('bill_due_reminder');
});

test('check-reminders sends overdue notifications for past-due bills', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 15));

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    Bill::factory()->for($ledger)->for($account)->create([
        'next_due_date' => CarbonImmutable::create(2026, 3, 12),
        'is_active' => true,
    ]);

    $this->artisan('bills:check-reminders')->assertSuccessful();

    expect($user->notifications()->count())->toBe(1);
    expect($user->notifications()->first()->data['type'])->toBe('bill_overdue');
});

test('check-reminders sends due-today notifications', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 15));

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    Bill::factory()->for($ledger)->for($account)->create([
        'next_due_date' => CarbonImmutable::create(2026, 3, 15),
        'is_active' => true,
    ]);

    $this->artisan('bills:check-reminders')->assertSuccessful();

    expect($user->notifications()->count())->toBe(1);
    expect($user->notifications()->first()->data['type'])->toBe('bill_due_reminder');
});

test('check-reminders does not create duplicate notifications on same day', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 15));

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    Bill::factory()->for($ledger)->for($account)->create([
        'next_due_date' => CarbonImmutable::create(2026, 3, 17),
        'is_active' => true,
    ]);

    $this->artisan('bills:check-reminders')->assertSuccessful();
    $this->artisan('bills:check-reminders')->assertSuccessful();

    expect($user->notifications()->count())->toBe(1);
});

test('check-reminders skips inactive bills', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 15));

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    Bill::factory()->for($ledger)->for($account)->create([
        'next_due_date' => CarbonImmutable::create(2026, 3, 17),
        'is_active' => false,
    ]);

    $this->artisan('bills:check-reminders')->assertSuccessful();

    expect($user->notifications()->count())->toBe(0);
});
