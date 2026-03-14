<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\Transaction;

test('ledger factories create a complete related graph', function () {
    $ledger = Ledger::factory()->create();
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

    expect($transaction->ledger->is($ledger))->toBeTrue()
        ->and($transaction->account->is($account))->toBeTrue()
        ->and($transaction->category->is($category))->toBeTrue()
        ->and($transaction->payee->is($payee))->toBeTrue();
});
