<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\User;

test('first transaction sets flash flag and updates onboarding data', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();
    $payee = Payee::factory()->for($ledger)->create();

    $response = $this->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'payee_id' => $payee->id,
            'transaction_type' => 'expense',
            'amount' => 10.00,
            'description' => 'First purchase',
            'transaction_date' => '2026-03-15',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('first_transaction', true);

    $user->refresh();
    expect($user->onboarding_data)->toBeArray()
        ->and($user->onboarding_data['first_transaction_celebrated'])->toBeTrue();
});

test('second transaction does not set flash flag', function () {
    $user = User::factory()->create([
        'onboarding_data' => ['first_transaction_celebrated' => true],
    ]);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();
    $payee = Payee::factory()->for($ledger)->create();

    $response = $this->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'payee_id' => $payee->id,
            'transaction_type' => 'expense',
            'amount' => 20.00,
            'description' => 'Second purchase',
            'transaction_date' => '2026-03-15',
        ]);

    $response->assertRedirect();
    $response->assertSessionMissing('first_transaction');
});

test('first transfer transaction also triggers celebration', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $accountFrom = Account::factory()->for($ledger)->for($accountType)->create();
    $accountTo = Account::factory()->for($ledger)->for($accountType)->create();

    $response = $this->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $accountFrom->id,
            'to_account_id' => $accountTo->id,
            'transaction_type' => 'transfer',
            'amount' => 50.00,
            'description' => 'Transfer funds',
            'transaction_date' => '2026-03-15',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('first_transaction', true);

    $user->refresh();
    expect($user->onboarding_data['first_transaction_celebrated'])->toBeTrue();
});

test('celebration preserves existing onboarding data', function () {
    $user = User::factory()->create([
        'onboarding_data' => ['some_other_key' => 'some_value'],
    ]);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $response = $this->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_type' => 'expense',
            'amount' => 15.00,
            'description' => 'Test',
            'transaction_date' => '2026-03-15',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('first_transaction', true);

    $user->refresh();
    expect($user->onboarding_data)
        ->toHaveKey('some_other_key', 'some_value')
        ->toHaveKey('first_transaction_celebrated', true);
});
