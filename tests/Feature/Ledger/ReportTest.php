<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('report page returns trend and category breakdown data', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create([
        'name' => 'Food',
    ]);

    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->create([
            'transaction_type' => 'expense',
            'amount' => '-25.00',
            'transaction_date' => '2026-03-13',
        ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/reports/index')
        ->has('monthlyTrend')
        ->has('categoryBreakdown')
        ->has('dateRange')
        ->has('creditAccounts')
        ->etc()
    );
});

test('monthly trend includes net field', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->create([
            'transaction_type' => 'expense',
            'amount' => '-100.00',
            'transaction_date' => '2026-03-01',
        ]);

    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->create([
            'transaction_type' => 'income',
            'amount' => '200.00',
            'transaction_date' => '2026-03-01',
        ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger).'?date_from=2026-03-01&date_to=2026-03-31');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/reports/index')
        ->has('monthlyTrend', 1)
        ->where('monthlyTrend.0.month', '2026-03')
        ->where('monthlyTrend.0.income', fn ($v) => (float) $v === 200.0)
        ->where('monthlyTrend.0.expense', fn ($v) => (float) $v === 100.0)
        ->where('monthlyTrend.0.net', fn ($v) => (float) $v === 100.0)
        ->etc()
    );
});

test('category breakdown includes percentage and parent_id fields', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $food = Category::factory()->for($ledger)->create(['name' => 'Food', 'parent_id' => null]);
    $transport = Category::factory()->for($ledger)->create(['name' => 'Transport', 'parent_id' => null]);

    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($food)
        ->create([
            'transaction_type' => 'expense',
            'amount' => '-75.00',
            'transaction_date' => '2026-03-01',
        ]);

    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($transport)
        ->create([
            'transaction_type' => 'expense',
            'amount' => '-25.00',
            'transaction_date' => '2026-03-01',
        ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger).'?date_from=2026-03-01&date_to=2026-03-31');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/reports/index')
        ->has('categoryBreakdown.items', 2)
        ->where('categoryBreakdown.items.0.name', 'Food')
        ->where('categoryBreakdown.items.0.total', fn ($v) => (float) $v === 75.0)
        ->where('categoryBreakdown.items.0.percentage', fn ($v) => (float) $v === 75.0)
        ->where('categoryBreakdown.items.0.parent_id', null)
        ->has('categoryBreakdown.items.0.color')
        ->has('categoryBreakdown.parents', 2)
        ->etc()
    );
});

test('category breakdown only includes expenses not income', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->create([
            'transaction_type' => 'income',
            'amount' => '500.00',
            'transaction_date' => '2026-03-01',
        ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger).'?date_from=2026-03-01&date_to=2026-03-31');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/reports/index')
        ->has('categoryBreakdown', 0)
        ->etc()
    );
});

test('report page includes credit statement cycle data', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->credit()->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create([
        'statement_day' => 15,
    ]);
    $category = Category::factory()->for($ledger)->create();

    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->create([
            'transaction_type' => 'expense',
            'amount' => '-80.00',
            'transaction_date' => '2026-01-10',
        ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('statementCycles', 1)
        ->where('statementCycles.0.start_date', '2025-12-16')
        ->where('statementCycles.0.end_date', '2026-01-15')
        ->where('statementCycles.0.account_name', $account->name)
        ->where('statementCycles.0.total', fn ($v) => (float) $v === -80.0)
        ->etc()
    );
});

test('report page returns credit accounts list', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->credit()->create();
    $creditAccount = Account::factory()->for($ledger)->for($accountType)->create([
        'statement_day' => 25,
    ]);
    $normalAccountType = AccountType::factory()->for($ledger)->create();
    Account::factory()->for($ledger)->for($normalAccountType)->create([
        'statement_day' => null,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('creditAccounts', 1)
        ->where('creditAccounts.0.id', $creditAccount->id)
        ->where('creditAccounts.0.name', $creditAccount->name)
        ->etc()
    );
});

test('report page returns dateRange prop with preset and echoed dates', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger).'?date_from=2026-03-01&date_to=2026-03-31');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/reports/index')
        ->where('dateRange.date_from', '2026-03-01')
        ->where('dateRange.date_to', '2026-03-31')
        ->has('dateRange.preset')
        ->etc()
    );
});

test('date range filters monthly trend correctly', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    // January transaction
    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->create([
            'transaction_type' => 'expense',
            'amount' => '-50.00',
            'transaction_date' => '2026-01-15',
        ]);

    // March transaction
    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->create([
            'transaction_type' => 'expense',
            'amount' => '-30.00',
            'transaction_date' => '2026-03-10',
        ]);

    // Request only March
    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger).'?date_from=2026-03-01&date_to=2026-03-31');

    $response->assertInertia(fn (Assert $page) => $page
        ->has('monthlyTrend', 1)
        ->where('monthlyTrend.0.month', '2026-03')
        ->where('monthlyTrend.0.expense', fn ($v) => (float) $v === 30.0)
        ->etc()
    );
});

test('another user cannot view reports', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();

    $this->actingAs($other)
        ->get(route('ledgers.reports.index', $ledger))
        ->assertForbidden();
});

test('report filters reject account ids from another ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    Account::factory()->for($ledger)->for($accountType)->create();

    $foreignLedger = Ledger::factory()->create();
    $foreignAccountType = AccountType::factory()->for($foreignLedger)->create();
    $foreignAccount = Account::factory()->for($foreignLedger)->for($foreignAccountType)->create();

    $this->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger).'?account_id='.$foreignAccount->id)
        ->assertSessionHasErrors('account_id');
});
