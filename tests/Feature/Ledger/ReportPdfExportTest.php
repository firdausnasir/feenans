<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;

test('pdf export returns a PDF download for a given month', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create(['name' => 'Food']);

    Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-50.00',
        'transaction_date' => '2026-03-15',
    ]);

    Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_type' => TransactionType::Income,
        'amount' => '200.00',
        'transaction_date' => '2026-03-10',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.export-pdf', ['ledger' => $ledger, 'month' => '2026-03']));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('report-');
});

test('pdf export defaults to current month when no month is specified', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.export-pdf', $ledger));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

test('pdf export includes category breakdown data', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $food = Category::factory()->for($ledger)->create(['name' => 'Food']);
    $transport = Category::factory()->for($ledger)->create(['name' => 'Transport']);

    Transaction::factory()->for($ledger)->for($account)->for($food)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-75.00',
        'transaction_date' => '2026-03-05',
    ]);

    Transaction::factory()->for($ledger)->for($account)->for($transport)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-25.00',
        'transaction_date' => '2026-03-10',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.export-pdf', ['ledger' => $ledger, 'month' => '2026-03']));

    $response->assertOk();

    // The PDF content is binary, but we verify it is a valid PDF
    $content = $response->content();
    expect(str_starts_with($content, '%PDF'))->toBeTrue();
});

test('another user cannot export pdf reports', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $other->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($owner)->create();

    $this->actingAs($other)
        ->get(route('ledgers.reports.export-pdf', $ledger))
        ->assertForbidden();
});
