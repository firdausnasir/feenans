<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->ledger = Ledger::factory()->for($this->user)->create();
    $this->accountType = AccountType::factory()->for($this->ledger)->create();
    $this->token = $this->user->createToken('test');
});

test('it lists budgets with stats', function () {
    $category = Category::factory()->for($this->ledger)->create();
    Budget::factory()->for($this->ledger)->for($category)->create([
        'amount' => 500,
        'period' => 'monthly',
        'start_date' => now()->startOfMonth()->toDateString(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/budgets?with_stats=1");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.category_name', $category->name)
        ->assertJsonStructure(['data' => [['id', 'amount', 'period', 'spent', 'remaining', 'percentage', 'status']]]);
});

test('it returns top N budgets', function () {
    $account = Account::factory()->for($this->ledger)->for($this->accountType)->create();

    $categoryA = Category::factory()->for($this->ledger)->create();
    $categoryB = Category::factory()->for($this->ledger)->create();
    $categoryC = Category::factory()->for($this->ledger)->create();

    Budget::factory()->for($this->ledger)->for($categoryA)->create([
        'amount' => 100,
        'period' => 'monthly',
        'start_date' => now()->startOfMonth()->toDateString(),
    ]);
    Budget::factory()->for($this->ledger)->for($categoryB)->create([
        'amount' => 200,
        'period' => 'monthly',
        'start_date' => now()->startOfMonth()->toDateString(),
    ]);
    Budget::factory()->for($this->ledger)->for($categoryC)->create([
        'amount' => 300,
        'period' => 'monthly',
        'start_date' => now()->startOfMonth()->toDateString(),
    ]);

    // Create expenses so categoryA has highest percentage (90/100 = 90%)
    Transaction::factory()->for($this->ledger)->for($account)->for($categoryA)->create([
        'amount' => -90,
        'transaction_date' => now()->toDateString(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/budgets?with_stats=1&top=2");

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');

    // First should be the highest percentage
    expect($response->json('data.0.percentage'))->toBeGreaterThanOrEqual($response->json('data.1.percentage'));
});

test('it creates budget with validation', function () {
    $category = Category::factory()->for($this->ledger)->create();

    $data = [
        'category_id' => $category->id,
        'amount' => 500.00,
        'period' => 'monthly',
        'start_date' => now()->startOfMonth()->toDateString(),
        'rollover' => false,
    ];

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/budgets", $data);

    $response->assertCreated()
        ->assertJsonPath('data.amount', '500.00')
        ->assertJsonPath('data.period', 'monthly');

    $this->assertDatabaseHas('budgets', [
        'ledger_id' => $this->ledger->id,
        'category_id' => $category->id,
        'amount' => 500.00,
    ]);
});

test('it returns 422 for invalid budget data', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/budgets", []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['amount', 'period', 'start_date']);
});

test('it updates budget', function () {
    $category = Category::factory()->for($this->ledger)->create();
    $budget = Budget::factory()->for($this->ledger)->for($category)->create([
        'amount' => 500,
        'period' => 'monthly',
        'start_date' => now()->startOfMonth()->toDateString(),
    ]);

    $data = [
        'category_id' => $category->id,
        'amount' => 750.00,
        'period' => 'monthly',
        'start_date' => now()->startOfMonth()->toDateString(),
        'rollover' => true,
    ];

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->putJson("/api/v1/ledgers/{$this->ledger->id}/budgets/{$budget->id}", $data);

    $response->assertSuccessful()
        ->assertJsonPath('data.amount', '750.00')
        ->assertJsonPath('data.rollover', true);
});

test('it deletes budget', function () {
    $category = Category::factory()->for($this->ledger)->create();
    $budget = Budget::factory()->for($this->ledger)->for($category)->create([
        'amount' => 500,
        'period' => 'monthly',
        'start_date' => now()->startOfMonth()->toDateString(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->deleteJson("/api/v1/ledgers/{$this->ledger->id}/budgets/{$budget->id}");

    $response->assertNoContent();

    expect(Budget::find($budget->id))->toBeNull();
});

test('it calculates correct spent amount', function () {
    $category = Category::factory()->for($this->ledger)->create();
    $account = Account::factory()->for($this->ledger)->for($this->accountType)->create();

    Budget::factory()->for($this->ledger)->for($category)->create([
        'amount' => 1000,
        'period' => 'monthly',
        'start_date' => now()->startOfMonth()->toDateString(),
    ]);

    // Create expense transactions in the current period
    Transaction::factory()->for($this->ledger)->for($account)->for($category)->create([
        'amount' => -150.50,
        'transaction_date' => now()->toDateString(),
    ]);
    Transaction::factory()->for($this->ledger)->for($account)->for($category)->create([
        'amount' => -249.50,
        'transaction_date' => now()->toDateString(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/budgets?with_stats=1");

    $response->assertSuccessful();

    $budget = $response->json('data.0');
    expect((float) $budget['spent'])->toBe(400.0);
    expect((float) $budget['remaining'])->toBe(600.0);
    expect((float) $budget['percentage'])->toBe(40.0);
    expect($budget['status'])->toBe('good');
});

test('it returns correct period bounds', function () {
    $category = Category::factory()->for($this->ledger)->create();
    Budget::factory()->for($this->ledger)->for($category)->create([
        'amount' => 500,
        'period' => 'monthly',
        'start_date' => now()->startOfMonth()->toDateString(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/budgets?with_stats=1");

    $response->assertSuccessful();

    $budget = $response->json('data.0');
    expect($budget['period_start'])->not->toBeNull();
    expect($budget['period_end'])->not->toBeNull();
});

test('it returns 401 when unauthenticated', function () {
    $response = $this->getJson("/api/v1/ledgers/{$this->ledger->id}/budgets");

    $response->assertUnauthorized();
});

test('it shows a single budget with stats', function () {
    $category = Category::factory()->for($this->ledger)->create();
    $budget = Budget::factory()->for($this->ledger)->for($category)->create([
        'amount' => 500,
        'period' => 'monthly',
        'start_date' => now()->startOfMonth()->toDateString(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/budgets/{$budget->id}");

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $budget->id)
        ->assertJsonPath('data.category_name', $category->name);
});
