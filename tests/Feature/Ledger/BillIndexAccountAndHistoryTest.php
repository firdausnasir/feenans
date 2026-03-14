<?php

use App\Enums\RecurrenceType;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bill;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;

test('bill index includes account name for each bill', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create(['name' => 'Main Checking']);

    Bill::factory()->for($ledger)->for($account)->create(['name' => 'Rent']);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.bills.index', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/bills/index')
        ->has('bills', 1)
        ->where('bills.0.account.name', 'Main Checking')
    );
});

test('bill index includes payment history transactions for each bill', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $bill = Bill::factory()->for($ledger)->for($account)->create([
        'name' => 'Internet',
        'amount' => 89.90,
        'recurrence_type' => RecurrenceType::Monthly,
        'recurrence_interval' => 1,
        'next_due_date' => CarbonImmutable::today()->addMonth(),
        'is_active' => true,
    ]);

    // Create transactions linked to this bill
    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->count(3)
        ->create([
            'bill_id' => $bill->id,
            'transaction_date' => CarbonImmutable::today()->subMonth(),
        ]);

    // Create an unrelated transaction (no bill_id)
    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->create(['bill_id' => null]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.bills.index', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/bills/index')
        ->has('bills', 1)
        ->has('bills.0.transactions', 3)
    );
});

test('bill index limits payment history to 10 transactions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $bill = Bill::factory()->for($ledger)->for($account)->create();

    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->count(15)
        ->create(['bill_id' => $bill->id]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.bills.index', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/bills/index')
        ->has('bills.0.transactions', 10)
    );
});

test('bill pay sets bill_id on the created transaction', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $bill = Bill::factory()->for($ledger)->for($account)->create([
        'name' => 'Electric',
        'amount' => 120.00,
        'recurrence_type' => RecurrenceType::Monthly,
        'recurrence_interval' => 1,
        'next_due_date' => CarbonImmutable::today(),
        'is_active' => true,
    ]);

    $this
        ->actingAs($user)
        ->post(route('ledgers.bills.pay', [$ledger, $bill]));

    $transaction = $ledger->transactions()->latest('id')->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->bill_id)->toBe($bill->id);
});

test('bill index transaction history includes account relationship', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create(['name' => 'Savings']);

    $bill = Bill::factory()->for($ledger)->for($account)->create();

    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->create(['bill_id' => $bill->id]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.bills.index', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/bills/index')
        ->has('bills.0.transactions.0.account')
        ->where('bills.0.transactions.0.account.name', 'Savings')
    );
});
