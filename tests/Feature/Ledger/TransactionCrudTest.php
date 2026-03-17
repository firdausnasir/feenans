<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;

test('users can create an income transaction', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_type' => 'income',
            'amount' => 500.00,
            'description' => 'Salary',
            'transaction_date' => '2026-03-01',
        ]);

    $response->assertRedirect();

    $transaction = $ledger->transactions()->first();
    expect($transaction)->not->toBeNull()
        ->and((float) $transaction->amount)->toBe(500.00)
        ->and($transaction->transaction_type)->toBe(TransactionType::Income);
});

test('users can create a transfer via HTTP', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $fromAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $toAccount = Account::factory()->for($ledger)->for($accountType)->create();

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'transaction_type' => 'transfer',
            'amount' => 150.00,
            'description' => 'Move to savings',
            'transaction_date' => '2026-03-01',
        ]);

    $response->assertRedirect();

    expect($ledger->transactions()->count())->toBe(2);

    $outgoing = $ledger->transactions()->where('amount', '<', 0)->first();
    $incoming = $ledger->transactions()->where('amount', '>', 0)->first();

    expect($outgoing->transfer_pair_id)->not->toBeNull()
        ->and($outgoing->transfer_pair_id)->toBe($incoming->transfer_pair_id)
        ->and((float) $outgoing->amount)->toBe(-150.00)
        ->and((float) $incoming->amount)->toBe(150.00)
        ->and($outgoing->account_id)->toBe($fromAccount->id)
        ->and($incoming->account_id)->toBe($toAccount->id);
});

test('transaction index filters by account', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $accountA = Account::factory()->for($ledger)->for($accountType)->create();
    $accountB = Account::factory()->for($ledger)->for($accountType)->create();

    Transaction::factory()->for($ledger)->for($accountA)->create([
        'description' => 'Account A transaction',
        'transaction_date' => now()->toDateString(),
    ]);
    Transaction::factory()->for($ledger)->for($accountB)->create([
        'description' => 'Account B transaction',
        'transaction_date' => now()->toDateString(),
    ]);

    $response = $this
        ->actingAs($user)
        ->getJson(route('api.v1.ledgers.transactions.index', [
            'ledger' => $ledger,
            'account_ids' => [$accountA->id],
        ]));

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.description', 'Account A transaction');
});

test('transaction index filters by search term', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    Transaction::factory()->for($ledger)->for($account)->create([
        'description' => 'Morning coffee',
        'transaction_date' => now()->toDateString(),
    ]);
    Transaction::factory()->for($ledger)->for($account)->create([
        'description' => 'Lunch at restaurant',
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
    $response->assertJsonPath('data.0.description', 'Morning coffee');
});

test('transaction index filters by transaction type', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-20.00',
        'transaction_date' => now()->toDateString(),
    ]);
    Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_type' => TransactionType::Income,
        'amount' => '100.00',
        'transaction_date' => now()->toDateString(),
    ]);

    $response = $this
        ->actingAs($user)
        ->getJson(route('api.v1.ledgers.transactions.index', [
            'ledger' => $ledger,
            'transaction_types' => ['income'],
        ]));

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
});

test('transaction index filters by category', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $catA = Category::factory()->for($ledger)->create();
    $catB = Category::factory()->for($ledger)->create();

    Transaction::factory()->for($ledger)->for($account)->for($catA)->create([
        'transaction_date' => now()->toDateString(),
    ]);
    Transaction::factory()->for($ledger)->for($account)->for($catB)->create([
        'transaction_date' => now()->toDateString(),
    ]);

    $response = $this
        ->actingAs($user)
        ->getJson(route('api.v1.ledgers.transactions.index', [
            'ledger' => $ledger,
            'category_ids' => [$catA->id],
        ]));

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
});

test('transaction index filters by payee', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $payeeA = Payee::factory()->for($ledger)->create();
    $payeeB = Payee::factory()->for($ledger)->create();

    Transaction::factory()->for($ledger)->for($account)->for($payeeA)->create([
        'transaction_date' => now()->toDateString(),
    ]);
    Transaction::factory()->for($ledger)->for($account)->for($payeeB)->create([
        'transaction_date' => now()->toDateString(),
    ]);

    $response = $this
        ->actingAs($user)
        ->getJson(route('api.v1.ledgers.transactions.index', [
            'ledger' => $ledger,
            'payee_ids' => [$payeeA->id],
        ]));

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
});

test('transaction store is forbidden for another users ledger', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $this->actingAs($intruder)
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $account->id,
            'transaction_type' => 'expense',
            'amount' => 10.00,
            'transaction_date' => '2026-03-01',
        ])
        ->assertForbidden();
});

test('transaction update is forbidden for another users ledger', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $transaction = Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-10.00',
    ]);

    $this->actingAs($intruder)
        ->put(route('api.v1.ledgers.transactions.update', [$ledger, $transaction]), [
            'account_id' => $account->id,
            'transaction_type' => 'expense',
            'amount' => 20.00,
            'transaction_date' => '2026-03-01',
        ])
        ->assertForbidden();
});

test('transaction destroy is forbidden for another users ledger', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $transaction = Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_type' => TransactionType::Expense,
    ]);

    $this->actingAs($intruder)
        ->delete(route('api.v1.ledgers.transactions.destroy', [$ledger, $transaction]))
        ->assertForbidden();
});

test('unauthenticated users cannot access transaction index', function () {
    $ledger = Ledger::factory()->create();

    $this->get(route('ledgers.transactions.index', $ledger))
        ->assertRedirect(route('login'));
});
