<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('deleted transactions appear in the trash view', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $transaction = Transaction::factory()->for($ledger)->for($account)->create([
        'description' => 'Archived coffee',
        'transaction_type' => TransactionType::Expense,
    ]);

    $this->actingAs($user)
        ->delete(route('ledgers.transactions.destroy', [$ledger, $transaction]))
        ->assertRedirect(route('ledgers.transactions.index', $ledger));

    $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);

    $this->actingAs($user)
        ->get(route('ledgers.transactions.trash', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/transactions/trash/index')
            ->has('transactions.data', 1)
            ->where('transactions.data.0.id', $transaction->id)
        );
});

test('users can restore a soft deleted transaction', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $transaction = Transaction::factory()->for($ledger)->for($account)->trashed()->create([
        'transaction_type' => TransactionType::Expense,
    ]);

    $this->actingAs($user)
        ->patch(route('ledgers.transactions.restore', [$ledger, $transaction]))
        ->assertRedirect(route('ledgers.transactions.trash', $ledger));

    $this->assertNotSoftDeleted('transactions', ['id' => $transaction->id]);
});

test('users can permanently delete a soft deleted transaction', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $transaction = Transaction::factory()->for($ledger)->for($account)->trashed()->create([
        'transaction_type' => TransactionType::Expense,
    ]);

    $this->actingAs($user)
        ->delete(route('ledgers.transactions.force-destroy', [$ledger, $transaction]))
        ->assertRedirect(route('ledgers.transactions.trash', $ledger));

    expect(Transaction::withTrashed()->find($transaction->id))->toBeNull();
});
