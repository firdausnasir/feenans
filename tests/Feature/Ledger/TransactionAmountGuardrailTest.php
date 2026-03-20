<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;

function validTransactionData(Account $account, Category $category, array $overrides = []): array
{
    return array_merge([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'transaction_type' => 'expense',
        'amount' => '50.00',
        'description' => 'Test transaction',
        'transaction_date' => '2026-03-15',
    ], $overrides);
}

test('storing a transaction with zero amount returns validation error', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), validTransactionData($account, $category, [
            'amount' => '0',
        ]));

    $response->assertSessionHasErrors([
        'amount' => 'Please enter an amount greater than zero.',
    ]);
});

test('storing a transaction with negative amount returns validation error', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), validTransactionData($account, $category, [
            'amount' => '-25.00',
        ]));

    $response->assertSessionHasErrors([
        'amount' => 'Please enter an amount greater than zero.',
    ]);
});

test('storing a transaction with positive amount is accepted', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create(['transaction_type' => 'expense']);

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), validTransactionData($account, $category, [
            'amount' => '25.50',
        ]));

    $response->assertSessionDoesntHaveErrors('amount');
    $response->assertRedirect();
});

test('split transaction amounts must be greater than zero', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create(['transaction_type' => 'expense']);

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), validTransactionData($account, $category, [
            'amount' => '100.00',
            'splits' => [
                ['amount' => '0', 'category_id' => $category->id, 'description' => 'Split A'],
                ['amount' => '100.00', 'category_id' => $category->id, 'description' => 'Split B'],
            ],
        ]));

    $response->assertSessionHasErrors('splits.0.amount');
});

test('updating a transaction with zero amount returns validation error', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create(['transaction_type' => 'expense']);

    $transaction = Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->create(['amount' => '50.00', 'transaction_date' => '2026-03-15']);

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.transactions.edit', [$ledger, $transaction]))
        ->put(route('ledgers.transactions.update', [$ledger, $transaction]), validTransactionData($account, $category, [
            'amount' => '0',
        ]));

    $response->assertRedirect(route('ledgers.transactions.edit', [$ledger, $transaction]))
        ->assertSessionHasErrors(['amount']);
});

test('updating a transaction with negative amount returns validation error', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create(['transaction_type' => 'expense']);

    $transaction = Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->create(['amount' => '50.00', 'transaction_date' => '2026-03-15']);

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.transactions.edit', [$ledger, $transaction]))
        ->put(route('ledgers.transactions.update', [$ledger, $transaction]), validTransactionData($account, $category, [
            'amount' => '-10.00',
        ]));

    $response->assertRedirect(route('ledgers.transactions.edit', [$ledger, $transaction]))
        ->assertSessionHasErrors(['amount']);
});
