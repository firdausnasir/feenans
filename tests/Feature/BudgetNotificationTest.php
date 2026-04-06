<?php

use App\Actions\Budgets\UseCases\CheckBudgetThresholdsAction;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\BudgetThresholdReached;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

test('checkThresholds sends threshold notification at 80 percent', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 15));

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create(['name' => 'Groceries']);

    Budget::query()->create([
        'ledger_id' => $ledger->id,
        'category_id' => $category->id,
        'amount' => 100,
        'period' => 'monthly',
        'start_date' => '2026-03-01',
        'is_active' => true,
        'rollover' => false,
    ]);

    Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'transaction_type' => 'expense',
        'amount' => -85,
        'transaction_date' => '2026-03-10',
    ]);

    app(CheckBudgetThresholdsAction::class)($ledger);

    $notifications = $user->notifications()->get();

    expect($notifications)->toHaveCount(1);
    expect($notifications->first()->data['type'])->toBe('budget_threshold');
    expect($notifications->first()->data['budget_name'])->toBe('Groceries');
});

test('checkThresholds sends exceeded notification at 100 percent', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 15));

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create(['name' => 'Dining']);

    Budget::query()->create([
        'ledger_id' => $ledger->id,
        'category_id' => $category->id,
        'amount' => 100,
        'period' => 'monthly',
        'start_date' => '2026-03-01',
        'is_active' => true,
        'rollover' => false,
    ]);

    Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'transaction_type' => 'expense',
        'amount' => -120,
        'transaction_date' => '2026-03-10',
    ]);

    app(CheckBudgetThresholdsAction::class)($ledger);

    $notifications = $user->notifications()->get();

    expect($notifications)->toHaveCount(1);
    expect($notifications->first()->data['type'])->toBe('budget_exceeded');
});

test('checkThresholds does not create duplicate notifications', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 15));

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create(['name' => 'Groceries']);

    Budget::query()->create([
        'ledger_id' => $ledger->id,
        'category_id' => $category->id,
        'amount' => 100,
        'period' => 'monthly',
        'start_date' => '2026-03-01',
        'is_active' => true,
        'rollover' => false,
    ]);

    Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'transaction_type' => 'expense',
        'amount' => -85,
        'transaction_date' => '2026-03-10',
    ]);

    $action = app(CheckBudgetThresholdsAction::class);
    $action($ledger);
    $action($ledger);

    expect($user->notifications()->count())->toBe(1);
});

test('checkThresholds does not notify when under 80 percent', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 15));

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    Budget::query()->create([
        'ledger_id' => $ledger->id,
        'category_id' => $category->id,
        'amount' => 100,
        'period' => 'monthly',
        'start_date' => '2026-03-01',
        'is_active' => true,
        'rollover' => false,
    ]);

    Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'transaction_type' => 'expense',
        'amount' => -50,
        'transaction_date' => '2026-03-10',
    ]);

    app(CheckBudgetThresholdsAction::class)($ledger);

    expect($user->notifications()->count())->toBe(0);
});

test('checkThresholds filters by category when provided', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 15));

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $groceries = Category::factory()->for($ledger)->create(['name' => 'Groceries']);
    $dining = Category::factory()->for($ledger)->create(['name' => 'Dining']);

    Budget::query()->create([
        'ledger_id' => $ledger->id,
        'category_id' => $groceries->id,
        'amount' => 100,
        'period' => 'monthly',
        'start_date' => '2026-03-01',
        'is_active' => true,
        'rollover' => false,
    ]);

    Budget::query()->create([
        'ledger_id' => $ledger->id,
        'category_id' => $dining->id,
        'amount' => 100,
        'period' => 'monthly',
        'start_date' => '2026-03-01',
        'is_active' => true,
        'rollover' => false,
    ]);

    Transaction::factory()->for($ledger)->for($account)->for($groceries)->create([
        'transaction_type' => 'expense',
        'amount' => -90,
        'transaction_date' => '2026-03-10',
    ]);

    Transaction::factory()->for($ledger)->for($account)->for($dining)->create([
        'transaction_type' => 'expense',
        'amount' => -90,
        'transaction_date' => '2026-03-10',
    ]);

    // Only check groceries category
    app(CheckBudgetThresholdsAction::class)($ledger, $groceries->id);

    $notifications = $user->notifications()->get();

    expect($notifications)->toHaveCount(1);
    expect($notifications->first()->data['budget_name'])->toBe('Groceries');
});

test('checkThresholds batches spend and unread notification lookups', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 15));

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $categories = Category::factory()->for($ledger)->count(3)->create();

    $budgets = $categories->map(function (Category $category) use ($account, $ledger) {
        $budget = Budget::query()->create([
            'ledger_id' => $ledger->id,
            'category_id' => $category->id,
            'amount' => 100.00,
            'period' => 'monthly',
            'start_date' => '2026-03-01',
            'is_active' => true,
            'rollover' => false,
        ]);

        Transaction::factory()->for($ledger)->for($account)->for($category)->create([
            'transaction_type' => 'expense',
            'amount' => -85,
            'transaction_date' => '2026-03-10',
        ]);

        return $budget;
    });

    foreach ($budgets as $budget) {
        $user->notify(new BudgetThresholdReached($budget, 85.0, 85.0));
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    app(CheckBudgetThresholdsAction::class)($ledger);

    $queryLog = collect(DB::getQueryLog());

    $transactionQueries = $queryLog->filter(function (array $query): bool {
        $sql = strtolower($query['query']);

        return str_starts_with($sql, 'select')
            && str_contains($sql, 'from "transactions"');
    });

    $notificationQueries = $queryLog->filter(function (array $query): bool {
        $sql = strtolower($query['query']);

        return str_starts_with($sql, 'select')
            && str_contains($sql, 'from "notifications"');
    });

    DB::disableQueryLog();

    expect($transactionQueries)->toHaveCount(1)
        ->and($notificationQueries)->toHaveCount(1)
        ->and($user->notifications()->count())->toBe(3);
});
