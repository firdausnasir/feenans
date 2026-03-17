<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Support\Str;

test('converting transfer to expense via HTTP deletes pair and updates transaction', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $fromAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $toAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();
    $pairId = (string) Str::uuid();

    $source = Transaction::factory()->for($ledger)->for($fromAccount)->create([
        'transaction_type' => TransactionType::Transfer,
        'amount' => '-100.00',
        'transfer_pair_id' => $pairId,
        'category_id' => null,
        'transaction_date' => '2026-03-01',
    ]);

    Transaction::factory()->for($ledger)->for($toAccount)->create([
        'transaction_type' => TransactionType::Transfer,
        'amount' => '100.00',
        'transfer_pair_id' => $pairId,
        'category_id' => null,
        'transaction_date' => '2026-03-01',
    ]);

    $response = $this
        ->actingAs($user)
        ->putJson(route('api.v1.ledgers.transactions.update', [$ledger, $source]), [
            'account_id' => $fromAccount->id,
            'category_id' => $category->id,
            'transaction_type' => 'expense',
            'amount' => 75.00,
            'description' => 'Converted to expense',
            'transaction_date' => '2026-03-01',
        ]);

    $response->assertOk();

    $source->refresh();
    expect($source->transaction_type)->toBe(TransactionType::Expense)
        ->and($source->transfer_pair_id)->toBeNull()
        ->and((float) $source->amount)->toBe(-75.00)
        ->and($source->category_id)->toBe($category->id);

    // The paired transaction should be deleted
    expect($ledger->transactions()->count())->toBe(1);
});

test('converting expense to transfer via HTTP creates paired transaction', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $fromAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $toAccount = Account::factory()->for($ledger)->for($accountType)->create();

    $transaction = Transaction::factory()->for($ledger)->for($fromAccount)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-50.00',
        'transfer_pair_id' => null,
        'transaction_date' => '2026-03-01',
    ]);

    $response = $this
        ->actingAs($user)
        ->putJson(route('api.v1.ledgers.transactions.update', [$ledger, $transaction]), [
            'account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'transaction_type' => 'transfer',
            'amount' => 200.00,
            'description' => 'Converted to transfer',
            'transaction_date' => '2026-03-01',
        ]);

    $response->assertOk();

    expect($ledger->transactions()->count())->toBe(2);

    $transaction->refresh();
    expect($transaction->transaction_type)->toBe(TransactionType::Transfer)
        ->and($transaction->transfer_pair_id)->not->toBeNull()
        ->and((float) $transaction->amount)->toBe(-200.00);

    $pair = Transaction::query()
        ->where('transfer_pair_id', $transaction->transfer_pair_id)
        ->where('id', '!=', $transaction->id)
        ->first();

    expect($pair)->not->toBeNull()
        ->and((float) $pair->amount)->toBe(200.00)
        ->and($pair->account_id)->toBe($toAccount->id);
});

test('service convertTransferToSingle deletes pair and updates transaction', function () {
    $ledger = Ledger::factory()->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $fromAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $toAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();
    $pairId = (string) Str::uuid();

    $source = Transaction::factory()->for($ledger)->for($fromAccount)->create([
        'transaction_type' => TransactionType::Transfer,
        'amount' => '-100.00',
        'transfer_pair_id' => $pairId,
        'category_id' => null,
        'transaction_date' => '2026-03-01',
    ]);

    Transaction::factory()->for($ledger)->for($toAccount)->create([
        'transaction_type' => TransactionType::Transfer,
        'amount' => '100.00',
        'transfer_pair_id' => $pairId,
        'category_id' => null,
        'transaction_date' => '2026-03-01',
    ]);

    $result = app(TransactionService::class)->convertTransferToSingle($source, [
        'account' => $fromAccount,
        'category' => $category,
        'payee' => null,
        'transaction_type' => TransactionType::Expense,
        'amount' => 80.00,
        'description' => 'Now an expense',
        'transaction_date' => '2026-03-01',
    ]);

    expect($result->transaction_type)->toBe(TransactionType::Expense)
        ->and($result->transfer_pair_id)->toBeNull()
        ->and((float) $result->amount)->toBe(-80.00)
        ->and($result->category_id)->toBe($category->id)
        ->and($ledger->transactions()->count())->toBe(1);
});

test('service convertSingleToTransfer creates paired transaction', function () {
    $ledger = Ledger::factory()->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $fromAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $toAccount = Account::factory()->for($ledger)->for($accountType)->create();

    $transaction = Transaction::factory()->for($ledger)->for($fromAccount)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-50.00',
        'transfer_pair_id' => null,
        'transaction_date' => '2026-03-01',
    ]);

    [$updated, $incoming] = app(TransactionService::class)->convertSingleToTransfer($transaction, $ledger, [
        'from_account' => $fromAccount,
        'to_account' => $toAccount,
        'amount' => 120.00,
        'description' => 'Now a transfer',
        'transaction_date' => '2026-03-01',
    ]);

    expect($updated->transaction_type)->toBe(TransactionType::Transfer)
        ->and($updated->transfer_pair_id)->not->toBeNull()
        ->and((float) $updated->amount)->toBe(-120.00)
        ->and($updated->category_id)->toBeNull()
        ->and($updated->payee_id)->toBeNull()
        ->and((float) $incoming->amount)->toBe(120.00)
        ->and($incoming->account_id)->toBe($toAccount->id)
        ->and($incoming->transfer_pair_id)->toBe($updated->transfer_pair_id)
        ->and($ledger->transactions()->count())->toBe(2);
});

test('service forceDelete permanently removes a regular transaction', function () {
    $ledger = Ledger::factory()->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $transaction = Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_type' => TransactionType::Expense,
        'transfer_pair_id' => null,
    ]);

    app(TransactionService::class)->forceDelete($transaction);

    expect(Transaction::find($transaction->id))->toBeNull();
});

test('service forceDelete permanently removes both paired transfer transactions', function () {
    $ledger = Ledger::factory()->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $fromAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $toAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $pairId = (string) Str::uuid();

    $source = Transaction::factory()->for($ledger)->for($fromAccount)->create([
        'transaction_type' => TransactionType::Transfer,
        'amount' => '-50.00',
        'transfer_pair_id' => $pairId,
        'category_id' => null,
    ]);

    Transaction::factory()->for($ledger)->for($toAccount)->create([
        'transaction_type' => TransactionType::Transfer,
        'amount' => '50.00',
        'transfer_pair_id' => $pairId,
        'category_id' => null,
    ]);

    app(TransactionService::class)->forceDelete($source);

    expect(Transaction::where('transfer_pair_id', $pairId)->count())->toBe(0);
});
