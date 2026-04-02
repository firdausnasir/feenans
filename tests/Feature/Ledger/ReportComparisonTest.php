<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

test('report page renders with comparison query params', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger).'?date_from=2026-03-01&date_to=2026-03-31&compare_start=2026-02-01&compare_end=2026-02-28');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/reports/index')
    );
});

test('report comparison payload preserves summary and delta contract', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 15));

    try {
        $user = User::factory()->create();
        $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
        $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
        $accountType = AccountType::factory()->for($ledger)->create();
        $account = Account::factory()->for($ledger)->for($accountType)->create();
        $category = Category::factory()->for($ledger)->create(['name' => 'Food']);

        Transaction::factory()->for($ledger)->for($account)->for($category)->create([
            'transaction_type' => TransactionType::Expense,
            'amount' => '-90.00',
            'transaction_date' => '2026-03-10',
        ]);

        Transaction::factory()->for($ledger)->for($account)->create([
            'transaction_type' => TransactionType::Income,
            'amount' => '300.00',
            'transaction_date' => '2026-03-08',
        ]);

        Transaction::factory()->for($ledger)->for($account)->for($category)->create([
            'transaction_type' => TransactionType::Expense,
            'amount' => '-60.00',
            'transaction_date' => '2026-02-10',
        ]);

        Transaction::factory()->for($ledger)->for($account)->create([
            'transaction_type' => TransactionType::Income,
            'amount' => '200.00',
            'transaction_date' => '2026-02-06',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('ledgers.reports.index', $ledger).'?date_from=2026-03-01&date_to=2026-03-31&compare_start=2026-02-01&compare_end=2026-02-28');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/reports/index')
            ->where('dateRange.date_from', '2026-03-01')
            ->where('dateRange.date_to', '2026-03-31')
            ->where('dateRange.preset', 'this_month')
            ->missing('report')
        );

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('report.comparison.current_period.from', '2026-03-01')
                ->where('report.comparison.current_period.to', '2026-03-31')
                ->where('report.comparison.compare_period.from', '2026-02-01')
                ->where('report.comparison.compare_period.to', '2026-02-28')
                ->where('report.comparison.summary.current_expense', fn (mixed $value): bool => (float) $value === 90.0)
                ->where('report.comparison.summary.compare_expense', fn (mixed $value): bool => (float) $value === 60.0)
                ->where('report.comparison.summary.expense_delta', fn (mixed $value): bool => (float) $value === 30.0)
                ->where('report.comparison.summary.current_income', fn (mixed $value): bool => (float) $value === 300.0)
                ->where('report.comparison.summary.compare_income', fn (mixed $value): bool => (float) $value === 200.0)
                ->where('report.comparison.summary.biggest_change.name', 'Food')
                ->where('report.comparison.categoryDeltas.0.name', 'Food')
                ->where('report.comparison.trendOverlay.0.current_expense', fn (mixed $value): bool => (float) $value === 90.0)
                ->where('report.comparison.trendOverlay.0.compare_expense', fn (mixed $value): bool => (float) $value === 60.0)
            )
        );
    } finally {
        CarbonImmutable::setTestNow();
    }
});
