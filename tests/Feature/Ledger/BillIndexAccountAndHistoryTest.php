<?php

use App\Enums\RecurrenceType;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bill;
use App\Models\Ledger;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

test('bill index renders successfully', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
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

test('bill index deferred bills include account data as a plain array', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $sourceAccount = Account::factory()->for($ledger)->for($accountType)->create(['name' => 'Main Checking']);
    $destinationAccount = Account::factory()->for($ledger)->for($accountType)->create(['name' => 'Savings']);

    Bill::factory()->for($ledger)->for($sourceAccount)->create([
        'name' => 'Allowance Transfer',
        'transaction_type' => 'transfer',
        'to_account_id' => $destinationAccount->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.bills.index', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/bills/index')
        ->missing('bills')
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->has('bills', 1, fn (Assert $bill) => $bill
                ->where('name', 'Allowance Transfer')
                ->where('account.name', 'Main Checking')
                ->where('to_account.name', 'Savings')
                ->etc()
            )
        )
    );
});

test('bill pay sets bill_id on the created transaction', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
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
