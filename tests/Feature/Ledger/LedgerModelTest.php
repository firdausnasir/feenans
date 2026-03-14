<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;

test('ledger models expose the expected relationships', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();
    $payee = Payee::factory()->for($ledger)->create();
    $transaction = Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->for($payee)
        ->create();

    expect($user->ledgers)->toHaveCount(1)
        ->and($ledger->user->is($user))->toBeTrue()
        ->and($ledger->accountTypes)->toHaveCount(1)
        ->and($ledger->accounts)->toHaveCount(1)
        ->and($ledger->categories)->toHaveCount(1)
        ->and($ledger->payees)->toHaveCount(1)
        ->and($ledger->transactions)->toHaveCount(1)
        ->and($account->ledger->is($ledger))->toBeTrue()
        ->and($account->accountType->is($accountType))->toBeTrue()
        ->and($transaction->ledger->is($ledger))->toBeTrue()
        ->and($transaction->account->is($account))->toBeTrue()
        ->and($transaction->category->is($category))->toBeTrue()
        ->and($transaction->payee->is($payee))->toBeTrue();
});
