<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('search filters transactions by description', function () {
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
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('transactions.data', 1)
                ->where('transactions.data.0.description', 'Morning coffee at cafe')
            )
        );
});

test('search filters transactions by notes', function () {
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
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('transactions.data', 1)
                ->where('transactions.data.0.description', 'Store purchase')
            )
        );
});

test('search returns all transactions when search is empty', function () {
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
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('transactions.data', 3)
            )
        );
});

test('transaction index page renders immediate filters and defers transaction results', function () {
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
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->has('transactions.data', 1)
            ->where('transactions.data.0.description', 'Test query result')
        )
    );
});
