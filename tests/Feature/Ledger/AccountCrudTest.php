<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('account index shows net worth summary', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $checkingType = AccountType::factory()->for($ledger)->create(['is_credit' => false]);
    $creditType = AccountType::factory()->for($ledger)->credit()->create(['is_credit' => true]);

    $checking = Account::factory()->for($ledger)->for($checkingType)->create([
        'initial_balance' => 1000.00,
    ]);
    $credit = Account::factory()->for($ledger)->for($creditType)->create([
        'initial_balance' => 0.00,
    ]);

    $category = Category::factory()->for($ledger)->create();

    // Add expense to credit card
    Transaction::factory()->for($ledger)->for($credit)->for($category)->create([
        'amount' => '-200.00',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.accounts.index', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/accounts/index')
        ->has('netWorth')
        ->where('netWorth.assets', fn ($v) => (float) $v === 1000.0)
        ->where('netWorth.liabilities', fn ($v) => (float) $v === -200.0)
        ->where('netWorth.net', fn ($v) => (float) $v === 800.0)
    );
});

test('account index shows all accounts with correct balance', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create([
        'name' => 'Savings',
        'initial_balance' => 500.00,
    ]);
    $category = Category::factory()->for($ledger)->create();

    Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'amount' => '200.00',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.accounts.index', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/accounts/index')
        ->has('accounts', 1)
        ->where('accounts.0.current_balance', fn ($v) => (float) $v === 700.0)
    );
});

test('account create page renders with account types', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    AccountType::factory()->for($ledger)->count(2)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.accounts.create', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/accounts/create')
        ->has('accountTypes', 2)
    );
});

test('account edit page renders with account data', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.accounts.edit', [$ledger, $account]));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/accounts/edit')
        ->has('account')
        ->has('accountTypes')
    );
});

test('account show includes monthly balances for credit account with statement info', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $creditType = AccountType::factory()->for($ledger)->credit()->create();
    $account = Account::factory()->for($ledger)->for($creditType)->create([
        'statement_day' => 15,
        'initial_balance' => 0.00,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.accounts.show', [$ledger, $account]));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/accounts/show')
        ->has('monthlyBalances')
        ->has('statementInfo')
    );
});
