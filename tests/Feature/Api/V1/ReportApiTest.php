<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->ledger = Ledger::factory()->for($this->user)->create(['cycle_start_day' => 1]);
    $this->accountType = AccountType::factory()->for($this->ledger)->create(['name' => 'Checking']);
    $this->account = Account::factory()->for($this->ledger)->for($this->accountType)->create(['initial_balance' => 0]);
    $this->token = $this->user->createToken('test');
});

test('it returns spending report with monthly trends', function () {
    $category = Category::factory()->for($this->ledger)->create();
    $payee = Payee::factory()->for($this->ledger)->create();
    $today = now();

    Transaction::factory()->for($this->ledger)->for($this->account)->for($category)->for($payee)->create([
        'amount' => -150.00,
        'transaction_type' => 'expense',
        'transaction_date' => $today->toDateString(),
    ]);

    Transaction::factory()->for($this->ledger)->for($this->account)->for($category)->for($payee)->create([
        'amount' => 500.00,
        'transaction_type' => 'income',
        'transaction_date' => $today->toDateString(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/reports/spending?date_from={$today->startOfMonth()->toDateString()}&date_to={$today->endOfMonth()->toDateString()}");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'monthly_trends',
                'category_breakdown',
                'payee_breakdown',
                'summary' => ['total_income', 'total_expense', 'net', 'transaction_count'],
                'date_range' => ['date_from', 'date_to', 'preset'],
            ],
        ]);

    expect($response->json('data.summary.total_income'))->toEqual(500);
    expect($response->json('data.summary.total_expense'))->toEqual(150);
    expect($response->json('data.summary.net'))->toEqual(350);
    expect($response->json('data.summary.transaction_count'))->toEqual(2);
});

test('it returns spending report filtered by accounts', function () {
    $category = Category::factory()->for($this->ledger)->create();
    $payee = Payee::factory()->for($this->ledger)->create();
    $today = now();

    $account2 = Account::factory()->for($this->ledger)->for($this->accountType)->create(['initial_balance' => 0]);

    Transaction::factory()->for($this->ledger)->for($this->account)->for($category)->for($payee)->create([
        'amount' => -100.00,
        'transaction_type' => 'expense',
        'transaction_date' => $today->toDateString(),
    ]);

    Transaction::factory()->for($this->ledger)->for($account2)->for($category)->for($payee)->create([
        'amount' => -200.00,
        'transaction_type' => 'expense',
        'transaction_date' => $today->toDateString(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/reports/spending?date_from={$today->startOfMonth()->toDateString()}&date_to={$today->endOfMonth()->toDateString()}&account_id={$this->account->id}");

    $response->assertSuccessful();

    expect($response->json('data.summary.total_expense'))->toEqual(100);
});

test('it returns category breakdown sorted by total', function () {
    $categoryA = Category::factory()->for($this->ledger)->create(['name' => 'Food']);
    $categoryB = Category::factory()->for($this->ledger)->create(['name' => 'Transport']);
    $payee = Payee::factory()->for($this->ledger)->create();
    $today = now();

    Transaction::factory()->for($this->ledger)->for($this->account)->for($categoryA)->for($payee)->create([
        'amount' => -50.00,
        'transaction_type' => 'expense',
        'transaction_date' => $today->toDateString(),
    ]);

    Transaction::factory()->for($this->ledger)->for($this->account)->for($categoryB)->for($payee)->create([
        'amount' => -200.00,
        'transaction_type' => 'expense',
        'transaction_date' => $today->toDateString(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/reports/spending?date_from={$today->startOfMonth()->toDateString()}&date_to={$today->endOfMonth()->toDateString()}");

    $response->assertSuccessful();

    $items = $response->json('data.category_breakdown.items');
    expect($items)->toHaveCount(2);
    expect($items[0]['total'])->toBeGreaterThanOrEqual($items[1]['total']);
});

test('it returns cash flow with cumulative', function () {
    $category = Category::factory()->for($this->ledger)->create();
    $payee = Payee::factory()->for($this->ledger)->create();
    $todayStr = now()->toDateString();
    $monthStart = now()->copy()->startOfMonth()->toDateString();
    $monthEnd = now()->copy()->endOfMonth()->toDateString();

    Transaction::factory()->for($this->ledger)->for($this->account)->for($category)->for($payee)->create([
        'amount' => 1000.00,
        'transaction_type' => 'income',
        'transaction_date' => $todayStr,
    ]);

    Transaction::factory()->for($this->ledger)->for($this->account)->for($category)->for($payee)->create([
        'amount' => -300.00,
        'transaction_type' => 'expense',
        'transaction_date' => $todayStr,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/reports/cash-flow?date_from={$monthStart}&date_to={$monthEnd}");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'daily_cash_flow' => [['date', 'income', 'expense', 'net', 'cumulative']],
                'upcoming_bills',
                'period_label',
            ],
        ]);

    $cashFlow = $response->json('data.daily_cash_flow');
    expect(count($cashFlow))->toBeGreaterThan(0);

    // Find the day with our transaction and verify cumulative is present
    $dayWithTx = collect($cashFlow)->firstWhere('date', $todayStr);
    expect($dayWithTx)->not()->toBeNull();
    expect($dayWithTx['income'])->toEqual(1000);
    expect($dayWithTx['expense'])->toEqual(300);
    expect($dayWithTx['net'])->toEqual(700);
});

test('it returns budget performance stats', function () {
    $category = Category::factory()->for($this->ledger)->create();
    $payee = Payee::factory()->for($this->ledger)->create();

    Budget::factory()->for($this->ledger)->for($category)->create([
        'amount' => 500,
        'period' => 'monthly',
        'start_date' => now()->startOfMonth()->toDateString(),
    ]);

    Transaction::factory()->for($this->ledger)->for($this->account)->for($category)->for($payee)->create([
        'amount' => -200.00,
        'transaction_type' => 'expense',
        'transaction_date' => now()->toDateString(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/reports/budget-performance");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'budget_stats' => [['id', 'category_name', 'amount', 'spent', 'remaining', 'percentage', 'period', 'status']],
                'period_label',
            ],
        ]);

    $stats = $response->json('data.budget_stats.0');
    expect($stats['amount'])->toEqual(500);
    expect($stats['spent'])->toEqual(200);
    expect($stats['remaining'])->toEqual(300);
    expect($stats['percentage'])->toEqual(40);
    expect($stats['status'])->toEqual('good');
});

test('it returns financial health summary', function () {
    Transaction::factory()->for($this->ledger)->for($this->account)
        ->for(Category::factory()->for($this->ledger))
        ->for(Payee::factory()->for($this->ledger))
        ->create([
            'amount' => 2000.00,
            'transaction_type' => 'income',
            'transaction_date' => now()->toDateString(),
        ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/reports/financial-health");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'net_worth_history' => [['month', 'assets', 'liabilities', 'net_worth']],
                'savings_rate_history' => [['month', 'income', 'expense', 'savings', 'rate']],
                'current_snapshot' => ['assets', 'liabilities', 'net_worth', 'debt_to_asset_ratio'],
            ],
        ]);

    expect($response->json('data.current_snapshot.net_worth'))->toBeGreaterThan(0);
});

test('it returns empty data for no transactions', function () {
    $today = now();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/reports/spending?date_from={$today->startOfMonth()->toDateString()}&date_to={$today->endOfMonth()->toDateString()}");

    $response->assertSuccessful();

    expect($response->json('data.summary.total_income'))->toEqual(0);
    expect($response->json('data.summary.total_expense'))->toEqual(0);
    expect($response->json('data.summary.net'))->toEqual(0);
    expect($response->json('data.summary.transaction_count'))->toEqual(0);
    expect($response->json('data.category_breakdown.items'))->toBeEmpty();
    expect($response->json('data.payee_breakdown'))->toBeEmpty();
});

test('it returns 401 when unauthenticated', function () {
    $response = $this->getJson("/api/v1/ledgers/{$this->ledger->id}/reports/spending");

    $response->assertUnauthorized();
});

test('it validates date range format', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/reports/spending?date_from=invalid&date_to=also-invalid");

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['date_from', 'date_to']);
});
