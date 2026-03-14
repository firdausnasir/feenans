<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('comparison returns null when no compare params provided', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger).'?date_from=2026-03-01&date_to=2026-03-31');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/reports/index')
        ->where('comparison', null)
        ->where('dateRange.compare_start', null)
        ->where('dateRange.compare_end', null)
        ->etc()
    );
});

test('comparison returns both periods data with deltas', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $food = Category::factory()->for($ledger)->create(['name' => 'Food']);
    $transport = Category::factory()->for($ledger)->create(['name' => 'Transport']);

    // Current period: March 2026
    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($food)
        ->create([
            'transaction_type' => 'expense',
            'amount' => '-150.00',
            'transaction_date' => '2026-03-10',
        ]);

    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($transport)
        ->create([
            'transaction_type' => 'expense',
            'amount' => '-50.00',
            'transaction_date' => '2026-03-15',
        ]);

    // Comparison period: February 2026
    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($food)
        ->create([
            'transaction_type' => 'expense',
            'amount' => '-100.00',
            'transaction_date' => '2026-02-10',
        ]);

    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($transport)
        ->create([
            'transaction_type' => 'expense',
            'amount' => '-80.00',
            'transaction_date' => '2026-02-15',
        ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger).'?date_from=2026-03-01&date_to=2026-03-31&compare_start=2026-02-01&compare_end=2026-02-28');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/reports/index')
        ->has('comparison')
        ->where('comparison.current_period.from', '2026-03-01')
        ->where('comparison.current_period.to', '2026-03-31')
        ->where('comparison.compare_period.from', '2026-02-01')
        ->where('comparison.compare_period.to', '2026-02-28')
        ->has('comparison.categoryDeltas', 2)
        ->has('comparison.trendOverlay')
        ->has('comparison.summary')
        ->where('comparison.summary.current_expense', fn ($v) => (float) $v === 200.0)
        ->where('comparison.summary.compare_expense', fn ($v) => (float) $v === 180.0)
        ->etc()
    );
});

test('comparison summary calculates correct percentage change', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create(['name' => 'Food']);

    // Current: 200 expense
    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->create([
            'transaction_type' => 'expense',
            'amount' => '-200.00',
            'transaction_date' => '2026-03-10',
        ]);

    // Previous: 100 expense
    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->create([
            'transaction_type' => 'expense',
            'amount' => '-100.00',
            'transaction_date' => '2026-02-10',
        ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger).'?date_from=2026-03-01&date_to=2026-03-31&compare_start=2026-02-01&compare_end=2026-02-28');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('comparison.summary.expense_percentage_change', fn ($v) => (float) $v === 100.0)
        ->where('comparison.summary.expense_delta', fn ($v) => (float) $v === 100.0)
        ->etc()
    );
});

test('comparison includes income data', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    // Current period income
    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->create([
            'transaction_type' => 'income',
            'amount' => '500.00',
            'transaction_date' => '2026-03-10',
        ]);

    // Previous period income
    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->create([
            'transaction_type' => 'income',
            'amount' => '300.00',
            'transaction_date' => '2026-02-10',
        ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger).'?date_from=2026-03-01&date_to=2026-03-31&compare_start=2026-02-01&compare_end=2026-02-28');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('comparison.summary.current_income', fn ($v) => (float) $v === 500.0)
        ->where('comparison.summary.compare_income', fn ($v) => (float) $v === 300.0)
        ->etc()
    );
});

test('comparison validates compare_end must be after compare_start', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);

    $this->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger).'?date_from=2026-03-01&date_to=2026-03-31&compare_start=2026-02-28&compare_end=2026-02-01')
        ->assertSessionHasErrors('compare_end');
});

test('comparison requires both compare_start and compare_end', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);

    $this->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger).'?date_from=2026-03-01&date_to=2026-03-31&compare_start=2026-02-01')
        ->assertSessionHasErrors('compare_end');
});

test('comparison echoes compare dates in dateRange prop', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger).'?date_from=2026-03-01&date_to=2026-03-31&compare_start=2026-02-01&compare_end=2026-02-28');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('dateRange.compare_start', '2026-02-01')
        ->where('dateRange.compare_end', '2026-02-28')
        ->etc()
    );
});

test('comparison category deltas sorted by absolute delta descending', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $food = Category::factory()->for($ledger)->create(['name' => 'Food']);
    $transport = Category::factory()->for($ledger)->create(['name' => 'Transport']);

    // Food: current 300, previous 100 -> delta 200
    Transaction::factory()->for($ledger)->for($account)->for($food)->create([
        'transaction_type' => 'expense',
        'amount' => '-300.00',
        'transaction_date' => '2026-03-10',
    ]);
    Transaction::factory()->for($ledger)->for($account)->for($food)->create([
        'transaction_type' => 'expense',
        'amount' => '-100.00',
        'transaction_date' => '2026-02-10',
    ]);

    // Transport: current 50, previous 10 -> delta 40
    Transaction::factory()->for($ledger)->for($account)->for($transport)->create([
        'transaction_type' => 'expense',
        'amount' => '-50.00',
        'transaction_date' => '2026-03-10',
    ]);
    Transaction::factory()->for($ledger)->for($account)->for($transport)->create([
        'transaction_type' => 'expense',
        'amount' => '-10.00',
        'transaction_date' => '2026-02-10',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger).'?date_from=2026-03-01&date_to=2026-03-31&compare_start=2026-02-01&compare_end=2026-02-28');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('comparison.categoryDeltas.0.name', 'Food')
        ->where('comparison.categoryDeltas.0.delta', fn ($v) => (float) $v === 200.0)
        ->where('comparison.categoryDeltas.1.name', 'Transport')
        ->where('comparison.categoryDeltas.1.delta', fn ($v) => (float) $v === 40.0)
        ->etc()
    );
});
