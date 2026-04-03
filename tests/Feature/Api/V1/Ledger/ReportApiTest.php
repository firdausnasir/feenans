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
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config()->set('app.paywall_enabled', true);
});

function reportApiRoute(string $name, mixed $parameters): string
{
    expect(app('router')->has($name))->toBeTrue();

    return route($name, $parameters);
}

test('report api spending index requires sanctum authentication', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->getJson(reportApiRoute('api.v1.ledgers.reports.index', $ledger))
        ->assertUnauthorized();
});

test('report api keeps expense totals absolute while signed contract normalization is deferred', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
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

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson(reportApiRoute('api.v1.ledgers.reports.index', [
        'ledger' => $ledger,
        'date_from' => '2026-03-01',
        'date_to' => '2026-03-31',
    ]));

    $response->assertSuccessful()
        ->assertJsonPath('data.date_range.date_from', '2026-03-01')
        ->assertJsonPath('data.date_range.date_to', '2026-03-31')
        ->assertJsonPath('data.category_breakdown.items.0.name', 'Food')
        ->assertJsonPath('data.summary.transaction_count', 2);

    expect($response->json('data.summary.total_income'))->toBe(200.0)
        ->and($response->json('data.summary.total_expense'))->toBe(50.0)
        ->and($response->json('data.summary.net'))->toBe(150.0);
});

test('token authenticated premium client can get financial health report json', function () {
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

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson(reportApiRoute('api.v1.ledgers.reports.financial-health', $ledger));

        $response->assertSuccessful()
            ->assertJsonCount(12, 'data.net_worth_history')
            ->assertJsonCount(12, 'data.savings_rate_history');

        expect($response->json('data.current_snapshot.assets'))->toBe(1150.0)
            ->and($response->json('data.current_snapshot.liabilities'))->toBe(300.0)
            ->and($response->json('data.current_snapshot.net_worth'))->toBe(850.0)
            ->and($response->json('data.current_snapshot.debt_to_asset_ratio'))->toBe(0.26);
    } finally {
        CarbonImmutable::setTestNow();
    }
});

test('token authenticated premium client can get budget performance report json', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 15));

    try {
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

        Sanctum::actingAs($user, ['*']);

        $this->getJson(reportApiRoute('api.v1.ledgers.reports.budget-performance', $ledger))
            ->assertSuccessful()
            ->assertJsonPath('data.period_label', 'Mar 01 – Mar 31, 2026')
            ->assertJsonPath('data.budget_stats.0.category_name', 'Groceries')
            ->assertJsonPath('data.budget_stats.0.period', 'monthly')
            ->assertJsonPath('data.budget_stats.0.status', 'warning');
    } finally {
        CarbonImmutable::setTestNow();
    }
});

test('cash flow report api keeps expense buckets absolute while signed contract normalization is deferred', function () {
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

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson(reportApiRoute('api.v1.ledgers.reports.cash-flow', [
            'ledger' => $ledger,
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
        ]));

        $response->assertSuccessful()
            ->assertJsonPath('data.period_label', 'Mar 01 – Mar 31, 2026')
            ->assertJsonCount(31, 'data.daily_cash_flow')
            ->assertJsonPath('data.upcoming_bills.0.name', 'Rent')
            ->assertJsonPath('data.upcoming_bills.0.account_name', 'Main Account');

        expect($response->json('data.daily_cash_flow.1.income'))->toBe(300.0)
            ->and($response->json('data.daily_cash_flow.3.expense'))->toBe(100.0);
    } finally {
        CarbonImmutable::setTestNow();
    }
});

test('free token authenticated client cannot access premium report api routes', function (string $routeName) {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    Sanctum::actingAs($user, ['*']);

    $this->getJson(reportApiRoute($routeName, $ledger))
        ->assertForbidden();
})->with([
    'spending' => 'api.v1.ledgers.reports.index',
    'financial health' => 'api.v1.ledgers.reports.financial-health',
    'budget performance' => 'api.v1.ledgers.reports.budget-performance',
    'cash flow' => 'api.v1.ledgers.reports.cash-flow',
]);

test('report api denies outsider ledger access', function () {
    $owner = User::factory()->create();
    $owner->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $outsider = User::factory()->create();
    $outsider->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($owner)->create();

    Sanctum::actingAs($outsider, ['*']);

    $this->getJson(reportApiRoute('api.v1.ledgers.reports.financial-health', $ledger))
        ->assertForbidden();
});
