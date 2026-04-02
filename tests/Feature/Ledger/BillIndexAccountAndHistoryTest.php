<?php

use App\Enums\RecurrenceType;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bill;
use App\Models\Ledger;
use App\Models\Transaction;
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

test('bill index account options only include visible accounts', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $visibleAccount = Account::factory()->for($ledger)->for($accountType)->create(['name' => 'Visible Account']);
    Account::factory()->for($ledger)->for($accountType)->create(['name' => 'Hidden Account', 'is_hidden' => true]);

    $this->actingAs($user)
        ->get(route('ledgers.bills.index', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('accounts', 1)
            ->where('accounts.0.id', $visibleAccount->id)
            ->where('accounts.0.name', 'Visible Account')
        );
});

test('bill index deferred bills include latest five bill linked transactions with account data and missed cycles', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create(['name' => 'Main Checking']);
    $bill = Bill::factory()->for($ledger)->for($account)->create([
        'name' => 'Gym Membership',
        'amount' => 80.00,
        'recurrence_type' => RecurrenceType::Monthly,
        'recurrence_interval' => 1,
        'next_due_date' => CarbonImmutable::today()->subDay()->toDateString(),
        'is_active' => true,
    ]);

    foreach (range(1, 6) as $dayOffset) {
        Transaction::factory()
            ->for($ledger)
            ->for($account)
            ->create([
                'bill_id' => $bill->id,
                'transaction_type' => 'expense',
                'amount' => -80.00,
                'transaction_date' => CarbonImmutable::today()->subDays($dayOffset)->toDateString(),
            ]);
    }

    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->create([
            'bill_id' => null,
            'transaction_type' => 'expense',
            'amount' => -15.00,
            'transaction_date' => CarbonImmutable::today()->toDateString(),
        ]);

    $this->actingAs($user)
        ->get(route('ledgers.bills.index', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('bills')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('bills', 1, fn (Assert $billData) => $billData
                    ->where('name', 'Gym Membership')
                    ->where('missed_cycles', 1)
                    ->has('transactions', 5, fn (Assert $transaction) => $transaction
                        ->where('account.name', 'Main Checking')
                        ->etc()
                    )
                    ->where('transactions.0.transaction_date', CarbonImmutable::today()->subDay()->toDateString())
                    ->where('transactions.4.transaction_date', CarbonImmutable::today()->subDays(5)->toDateString())
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
