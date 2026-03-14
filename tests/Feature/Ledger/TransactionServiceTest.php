<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
use App\Services\TransactionService;
use Carbon\CarbonImmutable;

test('transaction service stores a standard transaction', function () {
    $ledger = Ledger::factory()->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();
    $payee = Payee::factory()->for($ledger)->create();

    $transaction = app(TransactionService::class)->store([
        'ledger' => $ledger,
        'account' => $account,
        'category' => $category,
        'payee' => $payee,
        'transaction_type' => TransactionType::Expense,
        'amount' => -25.50,
        'description' => 'Lunch',
        'notes' => 'Team lunch',
        'transaction_date' => '2026-03-13',
    ]);

    expect($transaction->ledger->is($ledger))->toBeTrue()
        ->and($transaction->account->is($account))->toBeTrue()
        ->and((string) $transaction->amount)->toBe('-25.50');
});

test('transaction service creates a paired transfer', function () {
    $ledger = Ledger::factory()->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $fromAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $toAccount = Account::factory()->for($ledger)->for($accountType)->create();

    [$outgoing, $incoming] = app(TransactionService::class)->storeTransfer($ledger, [
        'from_account' => $fromAccount,
        'to_account' => $toAccount,
        'amount' => 100.00,
        'description' => 'Move funds',
        'transaction_date' => '2026-03-13',
        'notes' => null,
    ]);

    expect($outgoing->transfer_pair_id)->not->toBeNull()
        ->and($outgoing->transfer_pair_id)->toBe($incoming->transfer_pair_id)
        ->and((string) $outgoing->amount)->toBe('-100.00')
        ->and((string) $incoming->amount)->toBe('100.00');
});

test('transaction service calculates credit statement cycle bounds', function () {
    $ledger = Ledger::factory()->create();
    $accountType = AccountType::factory()->for($ledger)->credit()->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create([
        'statement_day' => 15,
    ]);

    [$start, $end] = app(TransactionService::class)->statementCycleBounds(
        $account,
        CarbonImmutable::parse('2026-01-10'),
    );

    expect($start->toDateString())->toBe('2025-12-16')
        ->and($end->toDateString())->toBe('2026-01-15');
});
