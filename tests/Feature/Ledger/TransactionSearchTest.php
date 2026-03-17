<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;

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
        ->getJson(route('api.v1.ledgers.transactions.index', [
            'ledger' => $ledger,
            'search' => 'coffee',
        ]));

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.description', 'Morning coffee at cafe');
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
        ->getJson(route('api.v1.ledgers.transactions.index', [
            'ledger' => $ledger,
            'search' => 'Birthday',
        ]));

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.description', 'Store purchase');
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
        ->getJson(route('api.v1.ledgers.transactions.index', $ledger));

    $response->assertSuccessful();
    $response->assertJsonCount(3, 'data');
});

test('transaction index page renders without data props', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.transactions.index', [
            'ledger' => $ledger,
            'search' => 'test query',
        ]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/transactions/index')
        ->has('ledger')
    );
});
