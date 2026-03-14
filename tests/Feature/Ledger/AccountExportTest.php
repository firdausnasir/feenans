<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;

test('account csv export returns csv with correct headers', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create([
        'initial_balance' => '1000.00',
    ]);

    Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-50.00',
        'description' => 'Groceries',
        'transaction_date' => '2026-03-10',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.accounts.export', [$ledger, $account]));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();
    expect($content)->toContain('Date,Description,Type,Category,Payee,Amount,"Running Balance",Notes');
    expect($content)->toContain('Groceries');
});

test('account csv export includes running balance calculated from initial balance', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create([
        'initial_balance' => '1000.00',
    ]);

    Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-200.00',
        'description' => 'First expense',
        'transaction_date' => '2026-03-01',
    ]);

    Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_type' => TransactionType::Income,
        'amount' => '500.00',
        'description' => 'Salary',
        'transaction_date' => '2026-03-05',
    ]);

    Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-100.00',
        'description' => 'Second expense',
        'transaction_date' => '2026-03-10',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.accounts.export', [$ledger, $account]));

    $content = $response->streamedContent();

    // initial_balance = 1000
    // After first expense (-200): 800.00
    // After salary (+500): 1300.00
    // After second expense (-100): 1200.00
    expect($content)->toContain('800.00');
    expect($content)->toContain('1300.00');
    expect($content)->toContain('1200.00');
});

test('account csv export respects date range filters', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create([
        'initial_balance' => '500.00',
    ]);

    Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-100.00',
        'description' => 'Before range',
        'transaction_date' => '2026-02-15',
    ]);

    Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-50.00',
        'description' => 'In range',
        'transaction_date' => '2026-03-10',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.accounts.export', [$ledger, $account]).'?date_from=2026-03-01&date_to=2026-03-31');

    $content = $response->streamedContent();
    expect($content)->toContain('In range');
    expect($content)->not->toContain('Before range');

    // Running balance should include the prior transaction: 500 - 100 = 400, then - 50 = 350
    expect($content)->toContain('350.00');
});

test('another user cannot export account csv', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $this->actingAs($other)
        ->get(route('ledgers.accounts.export', [$ledger, $account]))
        ->assertForbidden();
});
