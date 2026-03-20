<?php

use App\Enums\RecurrenceType;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bill;
use App\Models\Ledger;
use App\Models\User;
use Carbon\CarbonImmutable;

test('bill index renders successfully', function () {
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
        ->from(route('ledgers.bills.index', $ledger))
        ->post(route('ledgers.bills.pay', [$ledger, $bill]));

    $transaction = $ledger->transactions()->latest('id')->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->bill_id)->toBe($bill->id);
});
