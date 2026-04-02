<?php

use App\Actions\Budgets\Queries\GetBudgetPeriodBoundsQuery;
use App\Actions\Budgets\Queries\GetBudgetSpentQuery;
use App\Actions\Budgets\Queries\ListBudgetsQuery;
use App\Actions\Budgets\UseCases\StoreBudgetAction;
use App\Actions\Budgets\UseCases\UpdateBudgetAction;
use App\Data\Budgets\Input\StoreBudgetData;
use App\Data\Budgets\Input\UpdateBudgetData;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

test('store budget action stores a budget', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create();

    $budget = app(StoreBudgetAction::class)(new StoreBudgetData(
        category_id: $category->id,
        amount: 500.00,
        period: 'monthly',
        start_date: '2026-03-01',
        end_date: null,
        rollover: false,
        ledger: $ledger,
        user: $user,
    ));

    expect($budget)->toBeInstanceOf(Budget::class)
        ->and($budget->ledger_id)->toBe($ledger->id)
        ->and($budget->category_id)->toBe($category->id)
        ->and((string) $budget->amount)->toBe('500.00')
        ->and($budget->period)->toBe('monthly');
});

test('update budget action updates a budget', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create();

    $budget = Budget::query()->create([
        'ledger_id' => $ledger->id,
        'category_id' => $category->id,
        'amount' => 300.00,
        'period' => 'monthly',
        'start_date' => '2026-03-01',
        'is_active' => true,
        'rollover' => false,
    ]);

    $updated = app(UpdateBudgetAction::class)(new UpdateBudgetData(
        category_id: $category->id,
        amount: 600.00,
        period: 'weekly',
        start_date: '2026-03-01',
        end_date: null,
        rollover: false,
        ledger: $ledger,
        budget: $budget,
        user: $user,
    ));

    expect((string) $updated->amount)->toBe('600.00')
        ->and($updated->period)->toBe('weekly');
});

test('budget spend query returns total expenses for category in current period', function () {
    $ledger = Ledger::factory()->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $now = CarbonImmutable::today();
    ['start' => $start] = $ledger->cycleBounds($now);

    // Expense in current cycle
    Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-75.00',
        'transaction_date' => $start->addDays(1)->toDateString(),
    ]);

    // Another expense in current cycle
    Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-25.00',
        'transaction_date' => $start->addDays(2)->toDateString(),
    ]);

    // Income should NOT count
    Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'transaction_type' => TransactionType::Income,
        'amount' => '500.00',
        'transaction_date' => $start->addDays(3)->toDateString(),
    ]);

    $budget = Budget::query()->create([
        'ledger_id' => $ledger->id,
        'category_id' => $category->id,
        'amount' => 200.00,
        'period' => 'monthly',
        'start_date' => $start->toDateString(),
        'is_active' => true,
        'rollover' => false,
    ]);

    $spent = app(GetBudgetSpentQuery::class)($budget, $ledger);

    expect($spent)->toBe(100.0);
});

test('budget spend query for overall budget counts all expenses', function () {
    $ledger = Ledger::factory()->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $catA = Category::factory()->for($ledger)->create();
    $catB = Category::factory()->for($ledger)->create();

    $now = CarbonImmutable::today();
    ['start' => $start] = $ledger->cycleBounds($now);

    Transaction::factory()->for($ledger)->for($account)->for($catA)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-30.00',
        'transaction_date' => $start->addDay()->toDateString(),
    ]);

    Transaction::factory()->for($ledger)->for($account)->for($catB)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-20.00',
        'transaction_date' => $start->addDays(2)->toDateString(),
    ]);

    $budget = Budget::query()->create([
        'ledger_id' => $ledger->id,
        'category_id' => null,
        'amount' => 100.00,
        'period' => 'monthly',
        'start_date' => $start->toDateString(),
        'is_active' => true,
        'rollover' => false,
    ]);

    $spent = app(GetBudgetSpentQuery::class)($budget, $ledger);

    expect($spent)->toBe(50.0);
});

test('budget period bounds query returns weekly bounds for weekly period', function () {
    $ledger = Ledger::factory()->create();

    $budget = Budget::query()->create([
        'ledger_id' => $ledger->id,
        'amount' => 100.00,
        'period' => 'weekly',
        'start_date' => '2026-03-01',
        'is_active' => true,
        'rollover' => false,
    ]);

    [$start, $end] = app(GetBudgetPeriodBoundsQuery::class)($budget, $ledger);

    $today = CarbonImmutable::today();
    expect($start->toDateString())->toBe($today->startOfWeek()->toDateString())
        ->and($end->toDateString())->toBe($today->endOfWeek()->toDateString());
});

test('budget period bounds query returns yearly bounds for yearly period', function () {
    $ledger = Ledger::factory()->create();

    $budget = Budget::query()->create([
        'ledger_id' => $ledger->id,
        'amount' => 5000.00,
        'period' => 'yearly',
        'start_date' => '2026-01-01',
        'is_active' => true,
        'rollover' => false,
    ]);

    [$start, $end] = app(GetBudgetPeriodBoundsQuery::class)($budget, $ledger);

    $today = CarbonImmutable::today();
    expect($start->toDateString())->toBe($today->startOfYear()->toDateString())
        ->and($end->toDateString())->toBe($today->endOfYear()->toDateString());
});

test('budget list query returns enriched data', function () {
    $ledger = Ledger::factory()->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create(['name' => 'Groceries']);

    $now = CarbonImmutable::today();
    ['start' => $start] = $ledger->cycleBounds($now);

    Budget::query()->create([
        'ledger_id' => $ledger->id,
        'category_id' => $category->id,
        'amount' => 200.00,
        'period' => 'monthly',
        'start_date' => $start->toDateString(),
        'is_active' => true,
        'rollover' => false,
    ]);

    Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-150.00',
        'transaction_date' => $start->addDay()->toDateString(),
    ]);

    $stats = app(ListBudgetsQuery::class)($ledger)->map->toArray()->all();

    expect($stats)->toHaveCount(1)
        ->and($stats[0]['category_name'])->toBe('Groceries')
        ->and($stats[0]['amount'])->toBe(200.0)
        ->and($stats[0]['spent'])->toBe(150.0)
        ->and($stats[0]['remaining'])->toBe(50.0)
        ->and($stats[0]['percentage'])->toBe(75.0)
        ->and($stats[0]['status'])->toBe('warning');
});

test('budget list query returns over status when exceeded', function () {
    $ledger = Ledger::factory()->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $now = CarbonImmutable::today();
    ['start' => $start] = $ledger->cycleBounds($now);

    Budget::query()->create([
        'ledger_id' => $ledger->id,
        'category_id' => $category->id,
        'amount' => 100.00,
        'period' => 'monthly',
        'start_date' => $start->toDateString(),
        'is_active' => true,
        'rollover' => false,
    ]);

    Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-150.00',
        'transaction_date' => $start->addDay()->toDateString(),
    ]);

    $stats = app(ListBudgetsQuery::class)($ledger)->map->toArray()->all();

    expect($stats[0]['status'])->toBe('over')
        ->and($stats[0]['percentage'])->toBe(100)
        ->and($stats[0]['remaining'])->toBe(0);
});

test('budget list query skips inactive budgets', function () {
    $ledger = Ledger::factory()->create();
    $category = Category::factory()->for($ledger)->create();

    Budget::query()->create([
        'ledger_id' => $ledger->id,
        'category_id' => $category->id,
        'amount' => 200.00,
        'period' => 'monthly',
        'start_date' => '2026-03-01',
        'is_active' => false,
        'rollover' => false,
    ]);

    $stats = app(ListBudgetsQuery::class)($ledger)->map->toArray()->all();

    expect($stats)->toHaveCount(0);
});

test('budget list query batches spend calculations for budgets in the same period', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 15));

    $ledger = Ledger::factory()->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $categories = Category::factory()->for($ledger)->count(3)->create();

    foreach ($categories as $category) {
        Budget::query()->create([
            'ledger_id' => $ledger->id,
            'category_id' => $category->id,
            'amount' => 100.00,
            'period' => 'monthly',
            'start_date' => '2026-03-01',
            'is_active' => true,
            'rollover' => false,
        ]);

        Transaction::factory()->for($ledger)->for($account)->for($category)->create([
            'transaction_type' => TransactionType::Expense,
            'amount' => '-25.00',
            'transaction_date' => '2026-03-10',
        ]);
    }

    Budget::query()->create([
        'ledger_id' => $ledger->id,
        'category_id' => null,
        'amount' => 500.00,
        'period' => 'monthly',
        'start_date' => '2026-03-01',
        'is_active' => true,
        'rollover' => false,
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $stats = app(ListBudgetsQuery::class)($ledger)->map->toArray()->all();

    $transactionQueries = collect(DB::getQueryLog())
        ->filter(function (array $query): bool {
            $sql = strtolower($query['query']);

            return str_starts_with($sql, 'select')
                && str_contains($sql, 'from "transactions"');
        });

    DB::disableQueryLog();

    expect($stats)->toHaveCount(4)
        ->and($transactionQueries)->toHaveCount(1);
});
