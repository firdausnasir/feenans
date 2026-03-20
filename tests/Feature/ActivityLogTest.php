<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\ActivityLog;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;

test('creating transaction creates activity log entry', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $this->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $account->id,
            'transaction_type' => 'expense',
            'amount' => 20.25,
            'description' => 'Coffee',
            'transaction_date' => '2026-03-13',
        ])
        ->assertRedirect();

    $transaction = $ledger->transactions()->latest('id')->first();

    expect($transaction)->not->toBeNull();

    $activity = ActivityLog::query()->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->action)->toBe('created')
        ->and($activity->subject_type)->toBe(Transaction::class)
        ->and($activity->subject_id)->toBe($transaction->id)
        ->and($activity->user_id)->toBe($user->id);
});

test('updating transaction logs old and new values', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $transaction = Transaction::factory()->for($ledger)->for($account)->create([
        'description' => 'Coffee',
        'amount' => '-20.25',
    ]);

    $this->actingAs($user)
        ->from(route('ledgers.transactions.index', $ledger))
        ->put(route('ledgers.transactions.update', [$ledger, $transaction]), [
            'account_id' => $account->id,
            'category_id' => null,
            'payee_id' => null,
            'transaction_type' => 'expense',
            'amount' => 35.50,
            'description' => 'Groceries',
            'notes' => null,
            'transaction_date' => '2026-03-13',
        ])
        ->assertRedirect(route('ledgers.transactions.index', $ledger));

    $activity = ActivityLog::query()->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->action)->toBe('updated')
        ->and($activity->old_values['description'])->toBe('Coffee')
        ->and((string) $activity->old_values['amount'])->toBe('-20.25')
        ->and($activity->new_values['description'])->toBe('Groceries');
});

test('deleting transaction logs deletion', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $transaction = Transaction::factory()->for($ledger)->for($account)->create([
        'description' => 'Coffee',
    ]);

    $this->actingAs($user)
        ->from(route('ledgers.transactions.index', $ledger))
        ->delete(route('ledgers.transactions.destroy', [$ledger, $transaction]))
        ->assertRedirect(route('ledgers.transactions.index', $ledger));

    $activity = ActivityLog::query()->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->action)->toBe('deleted')
        ->and($activity->subject_type)->toBe(Transaction::class)
        ->and($activity->subject_id)->toBe($transaction->id)
        ->and($activity->old_values['description'])->toBe('Coffee');
});
