<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

test('report page renders successfully', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/reports/index')
    );
});

test('report page preserves cycle-aware date range and deferred spending payload', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 15));

    try {
        $user = User::factory()->create();
        $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
        $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 5]);
        $accountType = AccountType::factory()->for($ledger)->create();
        $account = Account::factory()->for($ledger)->for($accountType)->create();
        $category = Category::factory()->for($ledger)->create(['name' => 'Food']);

        Transaction::factory()->for($ledger)->for($account)->for($category)->create([
            'transaction_type' => TransactionType::Expense,
            'amount' => '-50.00',
            'transaction_date' => '2026-03-12',
        ]);

        Transaction::factory()->for($ledger)->for($account)->create([
            'transaction_type' => TransactionType::Income,
            'amount' => '200.00',
            'transaction_date' => '2026-03-20',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('ledgers.reports.index', $ledger));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/reports/index')
            ->where('dateRange.date_from', '2026-03-05')
            ->where('dateRange.date_to', '2026-04-04')
            ->where('dateRange.preset', 'this_month')
            ->where('dateRange.account_id', null)
            ->missing('report')
        );

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('report.date_range.date_from', '2026-03-05')
                ->where('report.date_range.date_to', '2026-04-04')
                ->where('report.date_range.preset', 'this_month')
                ->where('report.summary.total_income', fn (mixed $value): bool => (float) $value === 200.0)
                ->where('report.summary.total_expense', fn (mixed $value): bool => (float) $value === 50.0)
                ->where('report.summary.net', fn (mixed $value): bool => (float) $value === 150.0)
                ->where('report.summary.transaction_count', 2)
                ->has('report.monthly_trends', 1)
            )
        );
    } finally {
        CarbonImmutable::setTestNow();
    }
});

test('financial health report page renders successfully', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.financial-health', $ledger));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/reports/financial-health')
    );
});

test('financial health report returns deferred snapshot and history payload', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 15));

    try {
        $user = User::factory()->create();
        $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
        $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
        $assetType = AccountType::factory()->for($ledger)->create(['is_credit' => false]);
        $liabilityType = AccountType::factory()->for($ledger)->create(['is_credit' => true]);
        $assetAccount = Account::factory()->for($ledger)->for($assetType)->create([
            'initial_balance' => 1000.00,
        ]);
        Account::factory()->for($ledger)->for($liabilityType)->create([
            'initial_balance' => 300.00,
        ]);

        Transaction::factory()->for($ledger)->for($assetAccount)->create([
            'transaction_type' => TransactionType::Income,
            'amount' => '200.00',
            'transaction_date' => '2026-03-05',
        ]);

        Transaction::factory()->for($ledger)->for($assetAccount)->create([
            'transaction_type' => TransactionType::Expense,
            'amount' => '-50.00',
            'transaction_date' => '2026-03-08',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('ledgers.reports.financial-health', $ledger));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/reports/financial-health')
            ->missing('health')
        );

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('health.net_worth_history', 12)
                ->where('health.net_worth_history.11.month', '2026-03')
                ->where('health.net_worth_history.11.net_worth', fn (mixed $value): bool => (float) $value === 850.0)
                ->has('health.savings_rate_history', 12)
                ->where('health.savings_rate_history.11.rate', fn (mixed $value): bool => (float) $value === 75.0)
                ->where('health.current_snapshot.assets', fn (mixed $value): bool => (float) $value === 1150.0)
                ->where('health.current_snapshot.liabilities', fn (mixed $value): bool => (float) $value === 300.0)
                ->where('health.current_snapshot.net_worth', fn (mixed $value): bool => (float) $value === 850.0)
                ->where('health.current_snapshot.debt_to_asset_ratio', fn (mixed $value): bool => (float) $value === 0.26)
            )
        );
    } finally {
        CarbonImmutable::setTestNow();
    }
});

test('budget performance report page renders successfully', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.budget-performance', $ledger));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/reports/budget-performance')
    );
});

test('budget performance report returns mapped budget stats payload', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 15));

    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create(['name' => 'Groceries']);

    Budget::query()->create([
        'ledger_id' => $ledger->id,
        'category_id' => $category->id,
        'amount' => 200.00,
        'period' => 'monthly',
        'start_date' => '2026-03-01',
        'is_active' => true,
        'rollover' => false,
    ]);

    Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-150.00',
        'transaction_date' => '2026-03-10',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.budget-performance', $ledger));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/reports/budget-performance')
        ->missing('performance')
    );

    $response->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->where('performance.period_label', 'Mar 01 – Mar 31, 2026')
            ->has('performance.budget_stats', 1, fn (Assert $budgetStat) => $budgetStat
                ->where('category_name', 'Groceries')
                ->where('amount', fn (mixed $value): bool => (float) $value === 200.0)
                ->where('spent', fn (mixed $value): bool => (float) $value === 150.0)
                ->where('remaining', fn (mixed $value): bool => (float) $value === 50.0)
                ->where('percentage', fn (mixed $value): bool => (float) $value === 75.0)
                ->where('period', 'monthly')
                ->where('status', 'warning')
                ->etc()
            )
        )
    );
});

test('cash flow report page renders successfully', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.cash-flow', $ledger));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/reports/cash-flow')
    );
});

test('cash flow report returns deferred daily flow and upcoming bills payload', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 15));

    try {
        $user = User::factory()->create();
        $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
        $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
        $accountType = AccountType::factory()->for($ledger)->create();
        $account = Account::factory()->for($ledger)->for($accountType)->create(['name' => 'Main Account']);

        Transaction::factory()->for($ledger)->for($account)->create([
            'transaction_type' => TransactionType::Income,
            'amount' => '300.00',
            'transaction_date' => '2026-03-02',
        ]);

        Transaction::factory()->for($ledger)->for($account)->create([
            'transaction_type' => TransactionType::Expense,
            'amount' => '-100.00',
            'transaction_date' => '2026-03-04',
        ]);

        $ledger->bills()->create([
            'name' => 'Rent',
            'amount' => 80.00,
            'transaction_type' => TransactionType::Expense->value,
            'account_id' => $account->id,
            'category_id' => null,
            'payee_id' => null,
            'recurrence_type' => 'monthly',
            'recurrence_interval' => 1,
            'recurrence_day' => 1,
            'next_due_date' => '2026-04-01',
            'auto_create' => false,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('ledgers.reports.cash-flow', $ledger));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/reports/cash-flow')
            ->missing('cashFlow')
        );

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('cashFlow.period_label', 'Mar 01 – Mar 31, 2026')
                ->has('cashFlow.daily_cash_flow', 31)
                ->where('cashFlow.daily_cash_flow.1.income', fn (mixed $value): bool => (float) $value === 300.0)
                ->where('cashFlow.daily_cash_flow.3.expense', fn (mixed $value): bool => (float) $value === 100.0)
                ->has('cashFlow.upcoming_bills', 1)
                ->where('cashFlow.upcoming_bills.0.name', 'Rent')
                ->where('cashFlow.upcoming_bills.0.account_name', 'Main Account')
            )
        );
    } finally {
        CarbonImmutable::setTestNow();
    }
});

test('another user cannot view reports', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $other->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($owner)->create();

    $this->actingAs($other)
        ->get(route('ledgers.reports.index', $ledger))
        ->assertForbidden();
});
