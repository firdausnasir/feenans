<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->ledger = Ledger::factory()->for($this->user)->create(['cycle_start_day' => 1]);
    $this->accountType = AccountType::factory()->for($this->ledger)->create();
    $this->account = Account::factory()->for($this->ledger)->for($this->accountType)->create();
    $this->token = $this->user->createToken('test');
});

test('it returns income expense summary for cycle', function () {
    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->income()
        ->create([
            'amount' => 1000,
            'transaction_date' => '2025-03-15',
        ]);

    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->expense()
        ->create([
            'amount' => -250,
            'transaction_date' => '2025-03-10',
        ]);

    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->expense()
        ->create([
            'amount' => -150,
            'transaction_date' => '2025-03-20',
        ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/transactions/summary?date_from=2025-03-01&date_to=2025-03-31");

    $response->assertSuccessful();
    expect((float) $response->json('income'))->toBe(1000.0)
        ->and((float) $response->json('expense'))->toBe(400.0)
        ->and((float) $response->json('net'))->toBe(600.0);
});

test('it returns previous cycle comparison', function () {
    // Current period transactions
    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->income()
        ->create([
            'amount' => 2000,
            'transaction_date' => '2025-03-15',
        ]);

    // Previous period transactions
    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->income()
        ->create([
            'amount' => 1500,
            'transaction_date' => '2025-02-15',
        ]);

    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->expense()
        ->create([
            'amount' => -300,
            'transaction_date' => '2025-02-10',
        ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/transactions/summary?date_from=2025-03-01&date_to=2025-03-31");

    $response->assertSuccessful();
    expect((float) $response->json('income'))->toBe(2000.0)
        ->and((float) $response->json('prev_income'))->toBe(1500.0)
        ->and((float) $response->json('prev_expense'))->toBe(300.0);
});

test('it returns daily expense trend', function () {
    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->expense()
        ->create([
            'amount' => -100,
            'transaction_date' => '2025-01-01',
        ]);

    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->expense()
        ->create([
            'amount' => -200,
            'transaction_date' => '2025-01-02',
        ]);

    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->income()
        ->create([
            'amount' => 500,
            'transaction_date' => '2025-01-01',
        ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/transactions/daily-trend?date_from=2025-01-01&date_to=2025-01-03");

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.date', '2025-01-01')
        ->assertJsonPath('data.1.date', '2025-01-02');
    expect((float) $response->json('data.0.expense'))->toBe(100.0)
        ->and((float) $response->json('data.0.income'))->toBe(500.0)
        ->and((float) $response->json('data.1.expense'))->toBe(200.0)
        ->and((float) $response->json('data.2.expense'))->toBe(0.0);
});

test('it returns top spending categories', function () {
    $category1 = Category::factory()->for($this->ledger)->create(['name' => 'Food', 'color' => '#ff0000']);
    $category2 = Category::factory()->for($this->ledger)->create(['name' => 'Transport', 'color' => '#00ff00']);

    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($category1)
        ->expense()
        ->create([
            'amount' => -300,
            'transaction_date' => '2025-03-10',
        ]);

    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($category2)
        ->expense()
        ->create([
            'amount' => -200,
            'transaction_date' => '2025-03-15',
        ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/categories/top-spending?date_from=2025-03-01&date_to=2025-03-31");

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Food')
        ->assertJsonPath('data.0.color', '#ff0000')
        ->assertJsonPath('data.1.name', 'Transport');
    expect((float) $response->json('data.0.total'))->toBe(300.0)
        ->and((float) $response->json('data.1.total'))->toBe(200.0);

    // Verify percentages add up
    $percentages = collect($response->json('data'))->sum('percentage');
    expect((float) $percentages)->toBe(100.0);
});

test('it returns uncategorized transaction count', function () {
    // Uncategorized expense
    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->expense()
        ->create([
            'category_id' => null,
            'transaction_date' => '2025-03-10',
        ]);

    // Uncategorized expense
    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->expense()
        ->create([
            'category_id' => null,
            'transaction_date' => '2025-03-15',
        ]);

    // Categorized expense (should not count)
    $category = Category::factory()->for($this->ledger)->create();
    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($category)
        ->expense()
        ->create([
            'transaction_date' => '2025-03-12',
        ]);

    // Transfer without category (should not count)
    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->transferOut()
        ->create([
            'transaction_date' => '2025-03-14',
        ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/transactions/uncategorized-count?date_from=2025-03-01&date_to=2025-03-31");

    $response->assertSuccessful()
        ->assertJsonPath('count', 2);
});

test('it returns correct cycle dates for mid month start', function () {
    $ledger = Ledger::factory()->for($this->user)->create(['cycle_start_day' => 15]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$ledger->id}/cycle?offset=0");

    $response->assertSuccessful()
        ->assertJsonStructure(['cycle_start', 'cycle_end', 'prev_cycle_start', 'prev_cycle_end', 'offset'])
        ->assertJsonPath('offset', 0);

    $cycleStart = $response->json('cycle_start');
    expect((int) substr($cycleStart, -2))->toBe(15);
});

test('it returns correct cycle dates for first of month', function () {
    $ledger = Ledger::factory()->for($this->user)->create(['cycle_start_day' => 1]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$ledger->id}/cycle?offset=0");

    $response->assertSuccessful();

    $cycleStart = $response->json('cycle_start');
    $cycleEnd = $response->json('cycle_end');

    // First of month start means cycle_start day should be 01
    expect((int) substr($cycleStart, -2))->toBe(1);

    // End should be last day of that same month
    $endDate = CarbonImmutable::parse($cycleEnd);
    expect($endDate->day)->toBe($endDate->daysInMonth);
});
