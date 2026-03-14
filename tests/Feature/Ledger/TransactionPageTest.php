<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('transaction index page lists ledger transactions', function () {
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
            'description' => 'Groceries',
            'amount' => '-50.00',
            'transaction_date' => '2026-03-13',
        ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.transactions.index', $ledger));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/transactions/index')
        ->has('transactions.data', 1)
        ->where('transactions.data.0.description', 'Groceries')
        ->has('accounts')
        ->has('categories')
        ->has('payees')
        ->has('filters')
        ->etc()
    );
});
