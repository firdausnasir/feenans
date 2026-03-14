<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;

test('export transactions returns CSV download', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create(['name' => 'Food']);

    Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-25.00',
        'description' => 'Lunch',
        'transaction_date' => now()->toDateString(),
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.transactions.export', $ledger));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();
    expect($content)->toContain('Date,Description,Type,Account,Category,Payee,Amount,Notes');
    expect($content)->toContain('Lunch');
});

test('export transactions respects date filter', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-10.00',
        'description' => 'In range',
        'transaction_date' => now()->toDateString(),
    ]);

    Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-10.00',
        'description' => 'Out of range',
        'transaction_date' => now()->subYear()->toDateString(),
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.transactions.export', $ledger), [
            'date_from' => now()->subMonth()->toDateString(),
            'date_to' => now()->toDateString(),
        ]);

    $content = $response->streamedContent();
    expect($content)->toContain('In range');
    expect($content)->not->toContain('Out of range');
});

test('recurring income bill creates income transaction when paid', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $bill = \App\Models\Bill::factory()->for($ledger)->create([
        'account_id' => $account->id,
        'transaction_type' => \App\Enums\TransactionType::Income,
        'amount' => 3000.00,
        'name' => 'Salary',
        'next_due_date' => now()->toDateString(),
    ]);

    app(\App\Services\BillService::class)->payBill($bill);

    $transaction = $ledger->transactions()->where('description', 'Salary')->first();
    expect($transaction)->not->toBeNull();
    expect((float) $transaction->amount)->toBe(3000.00);
    expect($transaction->transaction_type)->toBe(\App\Enums\TransactionType::Income);
});
