<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
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
        ->has('spendingHeatmap')
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
        ->has('categoryBreakdown.items', 0)
        ->has('categoryBreakdown.parents', 0)
        ->etc()
    );
});

test('report page includes spending heatmap data', function () {
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
            'amount' => '-80.00',
            'transaction_date' => '2026-03-10',
        ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger).'?date_from=2026-03-01&date_to=2026-03-31');

    $response->assertInertia(fn (Assert $page) => $page
        ->has('spendingHeatmap', 1)
        ->where('spendingHeatmap.0.date', '2026-03-10')
        ->where('spendingHeatmap.0.amount', fn ($v) => (float) $v === 80.0)
        ->etc()
    );
});

test('financial health report page renders successfully', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.financial-health', $ledger));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/reports/financial-health')
        ->has('netWorthHistory')
        ->has('savingsRateHistory')
        ->has('currentSnapshot')
        ->etc()
    );
});

test('budget performance report page renders successfully', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.budget-performance', $ledger));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/reports/budget-performance')
        ->has('budgetStats')
        ->has('periodLabel')
        ->etc()
    );
});

test('cash flow report page renders successfully', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.cash-flow', $ledger));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/reports/cash-flow')
        ->has('dailyCashFlow')
        ->has('upcomingBills')
        ->has('periodLabel')
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

test('report page includes income category breakdown data', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $salary = Category::factory()->for($ledger)->create(['name' => 'Salary', 'parent_id' => null]);
    $freelance = Category::factory()->for($ledger)->create(['name' => 'Freelance', 'parent_id' => null]);

    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($salary)
        ->create([
            'transaction_type' => 'income',
            'amount' => '3000.00',
            'transaction_date' => '2026-03-01',
        ]);

    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($freelance)
        ->create([
            'transaction_type' => 'income',
            'amount' => '1000.00',
            'transaction_date' => '2026-03-05',
        ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger).'?date_from=2026-03-01&date_to=2026-03-31');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/reports/index')
        ->has('incomeCategoryBreakdown.items', 2)
        ->where('incomeCategoryBreakdown.items.0.name', 'Salary')
        ->where('incomeCategoryBreakdown.items.0.total', fn ($v) => (float) $v === 3000.0)
        ->where('incomeCategoryBreakdown.items.0.percentage', fn ($v) => (float) $v === 75.0)
        ->has('incomeCategoryBreakdown.parents', 2)
        ->etc()
    );
});

test('income category breakdown only includes income not expenses', function () {
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
            'amount' => '-500.00',
            'transaction_date' => '2026-03-01',
        ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger).'?date_from=2026-03-01&date_to=2026-03-31');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/reports/index')
        ->has('incomeCategoryBreakdown.items', 0)
        ->has('incomeCategoryBreakdown.parents', 0)
        ->etc()
    );
});

test('report page includes income payee breakdown data', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();
    $payee = Payee::factory()->for($ledger)->create(['name' => 'Employer']);

    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->for($payee)
        ->create([
            'transaction_type' => 'income',
            'amount' => '5000.00',
            'transaction_date' => '2026-03-01',
        ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger).'?date_from=2026-03-01&date_to=2026-03-31');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/reports/index')
        ->has('incomePayeeBreakdown', 1)
        ->where('incomePayeeBreakdown.0.name', 'Employer')
        ->where('incomePayeeBreakdown.0.total', fn ($v) => (float) $v === 5000.0)
        ->where('incomePayeeBreakdown.0.percentage', fn ($v) => (float) $v === 100.0)
        ->etc()
    );
});

test('income payee breakdown only includes income not expenses', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();
    $payee = Payee::factory()->for($ledger)->create(['name' => 'Store']);

    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->for($payee)
        ->create([
            'transaction_type' => 'expense',
            'amount' => '-200.00',
            'transaction_date' => '2026-03-01',
        ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger).'?date_from=2026-03-01&date_to=2026-03-31');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/reports/index')
        ->has('incomePayeeBreakdown', 0)
        ->etc()
    );
});
