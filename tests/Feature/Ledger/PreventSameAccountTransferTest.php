<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;

// ─── Store: same-account transfer validation ──────────────────────────────────

test('store rejects transfer when source and destination accounts are the same', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $account->id,
            'to_account_id' => $account->id,
            'transaction_type' => 'transfer',
            'amount' => 50.00,
            'transaction_date' => '2026-03-14',
        ]);

    $response->assertSessionHasErrors(['to_account_id']);
    expect($ledger->transactions()->count())->toBe(0);
});

test('store accepts transfer when source and destination accounts differ', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $source = Account::factory()->for($ledger)->for($accountType)->create();
    $destination = Account::factory()->for($ledger)->for($accountType)->create();

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $source->id,
            'to_account_id' => $destination->id,
            'transaction_type' => 'transfer',
            'amount' => 50.00,
            'transaction_date' => '2026-03-14',
        ]);

    $response->assertRedirect();
    expect($ledger->transactions()->count())->toBe(2);
});

test('store validation error message for same-account transfer is descriptive', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $account->id,
            'to_account_id' => $account->id,
            'transaction_type' => 'transfer',
            'amount' => 50.00,
            'transaction_date' => '2026-03-14',
        ]);

    $response->assertSessionHasErrors([
        'to_account_id' => 'The destination account must be different from the source account.',
    ]);
});

// ─── Update: same-account transfer validation ─────────────────────────────────

test('update rejects transfer when source and destination accounts are the same', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $source = Account::factory()->for($ledger)->for($accountType)->create();
    $destination = Account::factory()->for($ledger)->for($accountType)->create();

    $pairId = Str::uuid()->toString();

    $transaction = Transaction::factory()->for($ledger)->for($source)->create([
        'transaction_type' => 'transfer',
        'amount' => '-50.00',
        'transfer_pair_id' => $pairId,
        'transaction_date' => '2026-03-14',
    ]);

    Transaction::factory()->for($ledger)->for($destination)->create([
        'transaction_type' => 'transfer',
        'amount' => '50.00',
        'transfer_pair_id' => $pairId,
        'transaction_date' => '2026-03-14',
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('ledgers.transactions.update', [$ledger, $transaction]), [
            'account_id' => $source->id,
            'to_account_id' => $source->id,
            'transaction_type' => 'transfer',
            'amount' => 50.00,
            'transaction_date' => '2026-03-14',
        ]);

    $response->assertSessionHasErrors(['to_account_id']);
});

test('update accepts transfer when source and destination accounts differ', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $source = Account::factory()->for($ledger)->for($accountType)->create();
    $destination = Account::factory()->for($ledger)->for($accountType)->create();
    $newDestination = Account::factory()->for($ledger)->for($accountType)->create();

    $pairId = Str::uuid()->toString();

    $transaction = Transaction::factory()->for($ledger)->for($source)->create([
        'transaction_type' => 'transfer',
        'amount' => '-50.00',
        'transfer_pair_id' => $pairId,
        'transaction_date' => '2026-03-14',
    ]);

    Transaction::factory()->for($ledger)->for($destination)->create([
        'transaction_type' => 'transfer',
        'amount' => '50.00',
        'transfer_pair_id' => $pairId,
        'transaction_date' => '2026-03-14',
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('ledgers.transactions.update', [$ledger, $transaction]), [
            'account_id' => $source->id,
            'to_account_id' => $newDestination->id,
            'transaction_type' => 'transfer',
            'amount' => 50.00,
            'transaction_date' => '2026-03-14',
        ]);

    $response->assertRedirect();
});
