<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;

test('editing expense to transfer creates paired transaction', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $fromAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $toAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $transaction = Transaction::factory()->for($ledger)->for($fromAccount)->for($category)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-50.00',
        'description' => 'Was expense',
        'transaction_date' => '2026-03-13',
        'transfer_pair_id' => null,
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('ledgers.transactions.update', [$ledger, $transaction]), [
            'account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'transaction_type' => 'transfer',
            'amount' => 50.00,
            'description' => 'Now a transfer',
            'notes' => null,
            'transaction_date' => '2026-03-13',
        ]);

    $response->assertRedirect(route('ledgers.transactions.index', $ledger));

    $transaction->refresh();

    expect($transaction->transaction_type)->toBe(TransactionType::Transfer)
        ->and($transaction->transfer_pair_id)->not->toBeNull()
        ->and($transaction->category_id)->toBeNull()
        ->and($transaction->payee_id)->toBeNull()
        ->and((float) $transaction->amount)->toBe(-50.00)
        ->and($transaction->account_id)->toBe($fromAccount->id);

    $pair = Transaction::query()
        ->where('transfer_pair_id', $transaction->transfer_pair_id)
        ->where('id', '!=', $transaction->id)
        ->first();

    expect($pair)->not->toBeNull()
        ->and((float) $pair->amount)->toBe(50.00)
        ->and($pair->account_id)->toBe($toAccount->id)
        ->and($pair->description)->toBe('Now a transfer');
});

test('editing transfer to expense removes paired transaction', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $fromAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $toAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $pairId = (string) Str::uuid();

    $source = Transaction::factory()->for($ledger)->for($fromAccount)->create([
        'transaction_type' => TransactionType::Transfer,
        'amount' => '-75.00',
        'description' => 'Transfer',
        'transfer_pair_id' => $pairId,
        'category_id' => null,
        'transaction_date' => '2026-03-13',
    ]);

    $destination = Transaction::factory()->for($ledger)->for($toAccount)->create([
        'transaction_type' => TransactionType::Transfer,
        'amount' => '75.00',
        'description' => 'Transfer',
        'transfer_pair_id' => $pairId,
        'category_id' => null,
        'transaction_date' => '2026-03-13',
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('ledgers.transactions.update', [$ledger, $source]), [
            'account_id' => $fromAccount->id,
            'category_id' => $category->id,
            'payee_id' => null,
            'transaction_type' => 'expense',
            'amount' => 75.00,
            'description' => 'Now an expense',
            'notes' => null,
            'transaction_date' => '2026-03-13',
        ]);

    $response->assertRedirect(route('ledgers.transactions.index', $ledger));

    $source->refresh();

    expect($source->transaction_type)->toBe(TransactionType::Expense)
        ->and($source->transfer_pair_id)->toBeNull()
        ->and($source->category_id)->toBe($category->id)
        ->and((float) $source->amount)->toBe(-75.00);

    expect(Transaction::find($destination->id))->toBeNull();
});

test('editing transfer destination account updates both transactions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $fromAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $toAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $newToAccount = Account::factory()->for($ledger)->for($accountType)->create();

    $pairId = (string) Str::uuid();

    $source = Transaction::factory()->for($ledger)->for($fromAccount)->create([
        'transaction_type' => TransactionType::Transfer,
        'amount' => '-100.00',
        'description' => 'Transfer',
        'transfer_pair_id' => $pairId,
        'category_id' => null,
        'transaction_date' => '2026-03-13',
    ]);

    $destination = Transaction::factory()->for($ledger)->for($toAccount)->create([
        'transaction_type' => TransactionType::Transfer,
        'amount' => '100.00',
        'description' => 'Transfer',
        'transfer_pair_id' => $pairId,
        'category_id' => null,
        'transaction_date' => '2026-03-13',
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('ledgers.transactions.update', [$ledger, $source]), [
            'account_id' => $fromAccount->id,
            'to_account_id' => $newToAccount->id,
            'transaction_type' => 'transfer',
            'amount' => 150.00,
            'description' => 'Updated transfer',
            'notes' => null,
            'transaction_date' => '2026-03-14',
        ]);

    $response->assertRedirect(route('ledgers.transactions.index', $ledger));

    $source->refresh();
    $destination->refresh();

    expect((float) $source->amount)->toBe(-150.00)
        ->and($source->account_id)->toBe($fromAccount->id)
        ->and($source->description)->toBe('Updated transfer')
        ->and($source->transaction_date->toDateString())->toBe('2026-03-14');

    expect((float) $destination->amount)->toBe(150.00)
        ->and($destination->account_id)->toBe($newToAccount->id)
        ->and($destination->description)->toBe('Updated transfer')
        ->and($destination->transaction_date->toDateString())->toBe('2026-03-14');
});

test('editing income to transfer creates paired transaction', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $fromAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $toAccount = Account::factory()->for($ledger)->for($accountType)->create();

    $transaction = Transaction::factory()->for($ledger)->for($fromAccount)->create([
        'transaction_type' => TransactionType::Income,
        'amount' => '200.00',
        'description' => 'Was income',
        'transaction_date' => '2026-03-13',
        'transfer_pair_id' => null,
        'category_id' => null,
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('ledgers.transactions.update', [$ledger, $transaction]), [
            'account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'transaction_type' => 'transfer',
            'amount' => 200.00,
            'description' => 'Now a transfer',
            'notes' => null,
            'transaction_date' => '2026-03-13',
        ]);

    $response->assertRedirect(route('ledgers.transactions.index', $ledger));

    $transaction->refresh();

    expect($transaction->transaction_type)->toBe(TransactionType::Transfer)
        ->and($transaction->transfer_pair_id)->not->toBeNull()
        ->and((float) $transaction->amount)->toBe(-200.00)
        ->and($transaction->account_id)->toBe($fromAccount->id);

    $pair = Transaction::query()
        ->where('transfer_pair_id', $transaction->transfer_pair_id)
        ->where('id', '!=', $transaction->id)
        ->first();

    expect($pair)->not->toBeNull()
        ->and((float) $pair->amount)->toBe(200.00)
        ->and($pair->account_id)->toBe($toAccount->id);
});

test('transfer type requires to_account_id and rejects same account', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $transaction = Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-50.00',
        'transfer_pair_id' => null,
        'transaction_date' => '2026-03-13',
    ]);

    // Missing to_account_id
    $response = $this
        ->actingAs($user)
        ->put(route('ledgers.transactions.update', [$ledger, $transaction]), [
            'account_id' => $account->id,
            'transaction_type' => 'transfer',
            'amount' => 50.00,
            'transaction_date' => '2026-03-13',
        ]);

    $response->assertSessionHasErrors('to_account_id');

    // Same account as source and destination
    $response = $this
        ->actingAs($user)
        ->put(route('ledgers.transactions.update', [$ledger, $transaction]), [
            'account_id' => $account->id,
            'to_account_id' => $account->id,
            'transaction_type' => 'transfer',
            'amount' => 50.00,
            'transaction_date' => '2026-03-13',
        ]);

    $response->assertSessionHasErrors('to_account_id');
});
