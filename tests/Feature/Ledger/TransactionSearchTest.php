<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('search preserves description query in transaction shell props', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->create([
            'description' => 'Morning coffee at cafe',
            'transaction_date' => now()->toDateString(),
        ]);

    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->create([
            'description' => 'Grocery shopping',
            'notes' => 'Weekly supplies',
            'transaction_date' => now()->toDateString(),
        ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.transactions.index', [
            'ledger' => $ledger,
            'search' => 'coffee',
        ]));

    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/transactions/index')
            ->where('filters.search', 'coffee')
            ->missing('transactions')
        );

    $response->assertViewMissing('page.deferredProps');
});

test('search preserves notes query in transaction shell props', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->create([
            'description' => 'Store purchase',
            'notes' => 'Birthday gift for Alice',
            'transaction_date' => now()->toDateString(),
        ]);

    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->create([
            'description' => 'Online order',
            'notes' => 'Electronics',
            'transaction_date' => now()->toDateString(),
        ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.transactions.index', [
            'ledger' => $ledger,
            'search' => 'Birthday',
        ]));

    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/transactions/index')
            ->where('filters.search', 'Birthday')
            ->missing('transactions')
        );

    $response->assertViewMissing('page.deferredProps');
});

test('search leaves transaction shell props unfiltered when search is empty', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->count(3)
        ->create(['transaction_date' => now()->toDateString()]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.transactions.index', $ledger));

    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/transactions/index')
            ->where('filters.search', null)
            ->missing('transactions')
        );

    $response->assertViewMissing('page.deferredProps');
});

test('transaction index page renders shell filters without deferred transactions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'description' => 'Test query result',
        'transaction_date' => now()->toDateString(),
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.transactions.index', [
            'ledger' => $ledger,
            'search' => 'test query',
        ]));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/transactions/index')
        ->has('filters')
        ->where('filters.search', 'test query')
        ->has('accounts', 1)
        ->has('categories', 1)
        ->has('payees', 0)
        ->has('tags', 0)
        ->missing('transactions')
    );

    $response->assertViewMissing('page.deferredProps');
});

test('transaction index accepts comma separated account filter input in shell props', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $checking = Account::factory()->for($ledger)->for($accountType)->create([
        'name' => 'Checking',
    ]);
    $savings = Account::factory()->for($ledger)->for($accountType)->create([
        'name' => 'Savings',
    ]);
    $cash = Account::factory()->for($ledger)->for($accountType)->create([
        'name' => 'Cash',
    ]);
    $category = Category::factory()->for($ledger)->create();

    $checkingTransaction = Transaction::factory()->for($ledger)->for($checking)->for($category)->create([
        'description' => 'Checking transaction',
        'transaction_date' => '2026-03-20',
    ]);

    $savingsTransaction = Transaction::factory()->for($ledger)->for($savings)->for($category)->create([
        'description' => 'Savings transaction',
        'transaction_date' => '2026-03-19',
    ]);

    Transaction::factory()->for($ledger)->for($cash)->for($category)->create([
        'description' => 'Cash transaction',
        'transaction_date' => '2026-03-18',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.transactions.index', [
            'ledger' => $ledger,
            'account_ids' => $checking->id.','.$savings->id,
        ]));

    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/transactions/index')
            ->where('filters.account_ids', [
                (string) $checking->id,
                (string) $savings->id,
            ])
            ->missing('transactions')
        );

    $response->assertViewMissing('page.deferredProps');
});
