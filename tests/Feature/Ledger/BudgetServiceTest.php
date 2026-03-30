<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Services\BudgetService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

test('budget service stores a budget', function () {
    $ledger = Ledger::factory()->create();
    $category = Category::factory()->for($ledger)->create();

    $budget = app(BudgetService::class)->store($ledger, [
        'category_id' => $category->id,
        'amount' => 500.00,
        'period' => 'monthly',
        'start_date' => '2026-03-01',
        'end_date' => null,
        'is_active' => true,
        'rollover' => false,
    ]);

    expect($budget)->toBeInstanceOf(Budget::class)
        ->and($budget->ledger_id)->toBe($ledger->id)
        ->and($budget->category_id)->toBe($category->id)
        ->and((string) $budget->amount)->toBe('500.00')
        ->and($budget->period)->toBe('monthly');
});

test('budget service updates a budget', function () {
    $ledger = Ledger::factory()->create();
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

    $updated = app(BudgetService::class)->update($budget, [
        'category_id' => $category->id,
        'amount' => 600.00,
        'period' => 'weekly',
        'start_date' => '2026-03-01',
    ]);

    expect((string) $updated->amount)->toBe('600.00')
        ->and($updated->period)->toBe('weekly');
});

test('budget service getSpent returns total expenses for category in current period', function () {
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

    $spent = app(BudgetService::class)->getSpent($budget, $ledger);

    expect($spent)->toBe(100.0);
});

test('budget service getSpent for overall budget counts all expenses', function () {
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

    $spent = app(BudgetService::class)->getSpent($budget, $ledger);

    expect($spent)->toBe(50.0);
});

test('budget service getPeriodBounds returns weekly bounds for weekly period', function () {
    $ledger = Ledger::factory()->create();

    $budget = Budget::query()->create([
        'ledger_id' => $ledger->id,
        'amount' => 100.00,
        'period' => 'weekly',
        'start_date' => '2026-03-01',
        'is_active' => true,
        'rollover' => false,
    ]);

    [$start, $end] = app(BudgetService::class)->getPeriodBounds($budget, $ledger);

    $today = CarbonImmutable::today();
    expect($start->toDateString())->toBe($today->startOfWeek()->toDateString())
        ->and($end->toDateString())->toBe($today->endOfWeek()->toDateString());
});

test('budget service getPeriodBounds returns yearly bounds for yearly period', function () {
    $ledger = Ledger::factory()->create();

    $budget = Budget::query()->create([
        'ledger_id' => $ledger->id,
        'amount' => 5000.00,
        'period' => 'yearly',
        'start_date' => '2026-01-01',
        'is_active' => true,
        'rollover' => false,
    ]);

    [$start, $end] = app(BudgetService::class)->getPeriodBounds($budget, $ledger);

    $today = CarbonImmutable::today();
    expect($start->toDateString())->toBe($today->startOfYear()->toDateString())
        ->and($end->toDateString())->toBe($today->endOfYear()->toDateString());
});

test('budget service getBudgetsWithStats returns enriched data', function () {
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

    $stats = app(BudgetService::class)->getBudgetsWithStats($ledger);

    expect($stats)->toHaveCount(1)
        ->and($stats[0]['category_name'])->toBe('Groceries')
        ->and($stats[0]['amount'])->toBe(200.0)
        ->and($stats[0]['spent'])->toBe(150.0)
        ->and($stats[0]['remaining'])->toBe(50.0)
        ->and($stats[0]['percentage'])->toBe(75.0)
        ->and($stats[0]['status'])->toBe('warning');
});

test('budget service getBudgetsWithStats returns over status when exceeded', function () {
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

    $stats = app(BudgetService::class)->getBudgetsWithStats($ledger);

    expect($stats[0]['status'])->toBe('over')
        ->and($stats[0]['percentage'])->toBe(100)
        ->and($stats[0]['remaining'])->toBe(0);
});

test('budget service getBudgetsWithStats skips inactive budgets', function () {
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

    $stats = app(BudgetService::class)->getBudgetsWithStats($ledger);

    expect($stats)->toHaveCount(0);
});

test('budget service getBudgetsWithStats batches spend calculations for budgets in the same period', function () {
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

    $stats = app(BudgetService::class)->getBudgetsWithStats($ledger);

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
