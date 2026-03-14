<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Services\TransactionService;
use Carbon\CarbonImmutable;

test('transaction service stores transaction with splits', function () {
    $ledger = Ledger::factory()->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $catA = Category::factory()->for($ledger)->create(['name' => 'Food']);
    $catB = Category::factory()->for($ledger)->create(['name' => 'Drinks']);

    $transaction = app(TransactionService::class)->store([
        'ledger' => $ledger,
        'account' => $account,
        'category' => $catA,
        'transaction_type' => TransactionType::Expense,
        'amount' => -100.00,
        'description' => 'Dinner with splits',
        'transaction_date' => '2026-03-01',
        'splits' => [
            ['amount' => -60.00, 'category_id' => $catA->id, 'description' => 'Main course'],
            ['amount' => -40.00, 'category_id' => $catB->id, 'description' => 'Drinks'],
        ],
    ]);

    expect($transaction->splits)->toHaveCount(2)
        ->and((float) $transaction->splits[0]->amount)->toBe(-60.00)
        ->and($transaction->splits[0]->description)->toBe('Main course')
        ->and((float) $transaction->splits[1]->amount)->toBe(-40.00);
});

test('transaction service update replaces existing splits', function () {
    $ledger = Ledger::factory()->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();
    $newCategory = Category::factory()->for($ledger)->create();

    $transaction = app(TransactionService::class)->store([
        'ledger' => $ledger,
        'account' => $account,
        'category' => $category,
        'transaction_type' => TransactionType::Expense,
        'amount' => -100.00,
        'description' => 'Original',
        'transaction_date' => '2026-03-01',
        'splits' => [
            ['amount' => -100.00, 'category_id' => $category->id, 'description' => 'Old split'],
        ],
    ]);

    expect($transaction->splits)->toHaveCount(1);

    $updated = app(TransactionService::class)->update($transaction, [
        'account' => $account,
        'category' => $category,
        'amount' => -150.00,
        'description' => 'Updated',
        'transaction_date' => '2026-03-01',
        'splits' => [
            ['amount' => -80.00, 'category_id' => $category->id, 'description' => 'New A'],
            ['amount' => -70.00, 'category_id' => $newCategory->id, 'description' => 'New B'],
        ],
    ]);

    expect($updated->splits)->toHaveCount(2)
        ->and($updated->splits[0]->description)->toBe('New A')
        ->and($updated->splits[1]->description)->toBe('New B');
});

test('transaction service update clears splits when none provided', function () {
    $ledger = Ledger::factory()->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $transaction = app(TransactionService::class)->store([
        'ledger' => $ledger,
        'account' => $account,
        'category' => $category,
        'transaction_type' => TransactionType::Expense,
        'amount' => -100.00,
        'description' => 'With splits',
        'transaction_date' => '2026-03-01',
        'splits' => [
            ['amount' => -100.00, 'category_id' => $category->id, 'description' => 'Split'],
        ],
    ]);

    expect($transaction->splits)->toHaveCount(1);

    $updated = app(TransactionService::class)->update($transaction, [
        'account' => $account,
        'category' => $category,
        'amount' => -100.00,
        'description' => 'Without splits',
        'transaction_date' => '2026-03-01',
        'splits' => [],
    ]);

    expect($updated->splits)->toHaveCount(0);
});

test('transaction service normalizes income amount to positive', function () {
    $ledger = Ledger::factory()->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $transaction = app(TransactionService::class)->store([
        'ledger' => $ledger,
        'account' => $account,
        'transaction_type' => TransactionType::Income,
        'amount' => -300.00,
        'description' => 'Income with negative input',
        'transaction_date' => '2026-03-01',
    ]);

    expect((float) $transaction->amount)->toBe(300.00);
});

test('transaction service normalizes expense amount to negative', function () {
    $ledger = Ledger::factory()->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $transaction = app(TransactionService::class)->store([
        'ledger' => $ledger,
        'account' => $account,
        'transaction_type' => TransactionType::Expense,
        'amount' => 200.00,
        'description' => 'Expense with positive input',
        'transaction_date' => '2026-03-01',
    ]);

    expect((float) $transaction->amount)->toBe(-200.00);
});

test('transaction service statement cycle bounds after statement day', function () {
    $ledger = Ledger::factory()->create();
    $accountType = AccountType::factory()->for($ledger)->credit()->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create([
        'statement_day' => 15,
    ]);

    [$start, $end] = app(TransactionService::class)->statementCycleBounds(
        $account,
        CarbonImmutable::parse('2026-01-20'),
    );

    expect($start->toDateString())->toBe('2026-01-16')
        ->and($end->toDateString())->toBe('2026-02-15');
});
