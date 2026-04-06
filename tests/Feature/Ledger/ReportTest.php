<?php

use App\Actions\Reports\Queries\GetBudgetPerformancePageQuery;
use App\Actions\Reports\Queries\GetCashFlowPageQuery;
use App\Actions\Reports\Queries\GetFinancialHealthPageQuery;
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

        $response->assertViewMissing('page.deferredProps');
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

test('financial health report renders without deferred health payload', function () {
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

        $response->assertViewMissing('page.deferredProps');
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

test('budget performance report renders without deferred performance payload', function () {
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

    $response->assertViewMissing('page.deferredProps');
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

test('cash flow report renders without deferred cash flow payload', function () {
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

        $response->assertViewMissing('page.deferredProps');
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

test('financial health report page does not execute page bootstrap queries on initial page visit', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();

    $this->mock(GetFinancialHealthPageQuery::class)
        ->shouldNotReceive('__invoke');

    $this->actingAs($user)
        ->get(route('ledgers.reports.financial-health', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/reports/financial-health')
            ->missing('health')
        );
});

test('budget performance report page does not execute page bootstrap queries on initial page visit', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();

    $this->mock(GetBudgetPerformancePageQuery::class)
        ->shouldNotReceive('__invoke');

    $this->actingAs($user)
        ->get(route('ledgers.reports.budget-performance', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/reports/budget-performance')
            ->missing('performance')
        );
});

test('cash flow report page does not execute page bootstrap queries on initial page visit', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();

    $this->mock(GetCashFlowPageQuery::class)
        ->shouldNotReceive('__invoke');

    $this->actingAs($user)
        ->get(route('ledgers.reports.cash-flow', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/reports/cash-flow')
            ->missing('cashFlow')
        );
});
