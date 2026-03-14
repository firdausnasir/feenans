<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;

test('bulk change category updates all selected transactions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $originalCategory = Category::factory()->for($ledger)->create();
    $newCategory = Category::factory()->for($ledger)->create();

    $transactions = Transaction::factory()
        ->count(3)
        ->for($ledger)
        ->for($account)
        ->for($originalCategory)
        ->expense()
        ->create();

    $response = $this->actingAs($user)
        ->post(route('ledgers.transactions.bulk-update', $ledger), [
            'ids' => $transactions->pluck('id')->all(),
            'action' => 'change_category',
            'value' => $newCategory->id,
        ]);

    $response->assertRedirect();

    foreach ($transactions as $transaction) {
        expect($transaction->fresh()->category_id)->toBe($newCategory->id);
    }
});

test('bulk change account updates all selected transactions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $originalAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $newAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $transactions = Transaction::factory()
        ->count(3)
        ->for($ledger)
        ->for($originalAccount)
        ->for($category)
        ->expense()
        ->create();

    $response = $this->actingAs($user)
        ->post(route('ledgers.transactions.bulk-update', $ledger), [
            'ids' => $transactions->pluck('id')->all(),
            'action' => 'change_account',
            'value' => $newAccount->id,
        ]);

    $response->assertRedirect();

    foreach ($transactions as $transaction) {
        expect($transaction->fresh()->account_id)->toBe($newAccount->id);
    }
});

test('bulk change payee updates all selected transactions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();
    $originalPayee = Payee::factory()->for($ledger)->create();
    $newPayee = Payee::factory()->for($ledger)->create();

    $transactions = Transaction::factory()
        ->count(3)
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->for($originalPayee)
        ->expense()
        ->create();

    $response = $this->actingAs($user)
        ->post(route('ledgers.transactions.bulk-update', $ledger), [
            'ids' => $transactions->pluck('id')->all(),
            'action' => 'change_payee',
            'value' => $newPayee->id,
        ]);

    $response->assertRedirect();

    foreach ($transactions as $transaction) {
        expect($transaction->fresh()->payee_id)->toBe($newPayee->id);
    }
});

test('bulk update skips transfer transactions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();
    $newCategory = Category::factory()->for($ledger)->create();
    $pairId = (string) Str::uuid();

    $expense = Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->expense()
        ->create();

    $transfer = Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->transferOut()
        ->create(['transfer_pair_id' => $pairId]);

    $response = $this->actingAs($user)
        ->post(route('ledgers.transactions.bulk-update', $ledger), [
            'ids' => [$expense->id, $transfer->id],
            'action' => 'change_category',
            'value' => $newCategory->id,
        ]);

    $response->assertRedirect();

    expect($expense->fresh()->category_id)->toBe($newCategory->id)
        ->and($transfer->fresh()->category_id)->toBeNull();
});

test('bulk update rejects cross-ledger values', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $foreignLedger = Ledger::factory()->create();
    $foreignCategory = Category::factory()->for($foreignLedger)->create();

    $transaction = Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->expense()
        ->create();

    $response = $this->actingAs($user)
        ->post(route('ledgers.transactions.bulk-update', $ledger), [
            'ids' => [$transaction->id],
            'action' => 'change_category',
            'value' => $foreignCategory->id,
        ]);

    $response->assertSessionHasErrors(['value']);
});

test('bulk update rejects invalid action', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $transaction = Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->expense()
        ->create();

    $response = $this->actingAs($user)
        ->post(route('ledgers.transactions.bulk-update', $ledger), [
            'ids' => [$transaction->id],
            'action' => 'invalid_action',
            'value' => $category->id,
        ]);

    $response->assertSessionHasErrors(['action']);
});

test('bulk delete with confirmation deletes all selected transactions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $transactions = Transaction::factory()
        ->count(3)
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->expense()
        ->create();

    $response = $this->actingAs($user)
        ->post(route('ledgers.transactions.bulk-destroy', $ledger), [
            'ids' => $transactions->pluck('id')->all(),
        ]);

    $response->assertRedirect();

    foreach ($transactions as $transaction) {
        expect($transaction->fresh()?->trashed())->toBeTrue();
    }
});
