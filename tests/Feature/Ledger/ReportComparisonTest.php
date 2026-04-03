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
use Laravel\Sanctum\Sanctum;

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

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson(
            route('api.v1.ledgers.reports.index', $ledger)
            .'?date_from=2026-03-01&date_to=2026-03-31&compare_start=2026-02-01&compare_end=2026-02-28'
        );

        $response->assertSuccessful()
            ->assertJsonPath('data.comparison.current_period.from', '2026-03-01')
            ->assertJsonPath('data.comparison.current_period.to', '2026-03-31')
            ->assertJsonPath('data.comparison.compare_period.from', '2026-02-01')
            ->assertJsonPath('data.comparison.compare_period.to', '2026-02-28')
            ->assertJsonPath('data.comparison.categoryDeltas.0.name', 'Food')
            ->assertJsonPath('data.comparison.trendOverlay.0.current_expense', 90.0)
            ->assertJsonPath('data.comparison.trendOverlay.0.compare_expense', 60.0);

        expect($response->json('data.comparison.summary.current_expense'))->toBe(90.0)
            ->and($response->json('data.comparison.summary.compare_expense'))->toBe(60.0)
            ->and($response->json('data.comparison.summary.expense_delta'))->toBe(30.0)
            ->and($response->json('data.comparison.summary.current_income'))->toBe(300.0)
            ->and($response->json('data.comparison.summary.compare_income'))->toBe(200.0)
            ->and($response->json('data.comparison.summary.biggest_change.name'))->toBe('Food');
    } finally {
        CarbonImmutable::setTestNow();
    }
});
