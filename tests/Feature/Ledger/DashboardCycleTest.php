<?php

use App\Enums\TransactionType;
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
    $this->category = Category::factory()->for($this->ledger)->create();
    $this->token = $this->user->createToken('test');
});

test('cycle endpoint supports offset navigation to previous month', function () {
    $now = CarbonImmutable::now();
    $lastMonth = $now->subMonth();
    ['start' => $lastStart, 'end' => $lastEnd] = $this->ledger->cycleBounds($lastMonth);

    Transaction::factory()->for($this->ledger)->for($this->account)->for($this->category)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-99.00',
        'transaction_date' => $lastStart->addDay()->toDateString(),
    ]);

    // Verify cycle endpoint returns correct dates for offset -1
    $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/cycle?offset=-1")
        ->assertSuccessful()
        ->assertJson([
            'cycle_start' => $lastStart->toDateString(),
            'cycle_end' => $lastEnd->toDateString(),
            'offset' => -1,
        ]);

    // Verify summary scoped to previous cycle
    $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/transactions/summary?date_from={$lastStart->toDateString()}&date_to={$lastEnd->toDateString()}")
        ->assertSuccessful()
        ->assertJson([
            'expense' => 99.0,
        ]);
});

test('daily trend endpoint returns data', function () {
    $now = CarbonImmutable::now();
    ['start' => $start, 'end' => $end] = $this->ledger->cycleBounds($now);

    Transaction::factory()->for($this->ledger)->for($this->account)->for($this->category)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-20.00',
        'transaction_date' => $start->addDay()->toDateString(),
    ]);

    $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/transactions/daily-trend?date_from={$start->toDateString()}&date_to={$end->toDateString()}")
        ->assertSuccessful()
        ->assertJsonStructure(['data' => [['date', 'expense', 'income']]]);
});

test('top spending endpoint returns categories', function () {
    $namedCategory = Category::factory()->for($this->ledger)->create(['name' => 'Food']);

    $now = CarbonImmutable::now();
    ['start' => $start, 'end' => $end] = $this->ledger->cycleBounds($now);

    Transaction::factory()->for($this->ledger)->for($this->account)->for($namedCategory)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-50.00',
        'transaction_date' => $start->addDay()->toDateString(),
    ]);

    $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/categories/top-spending?date_from={$start->toDateString()}&date_to={$end->toDateString()}")
        ->assertSuccessful()
        ->assertJsonFragment([
            'name' => 'Food',
            'total' => 50.0,
        ]);
});

test('dashboard stores current ledger id in session', function () {
    $this->actingAs($this->user)
        ->get(route('ledgers.dashboard', $this->ledger))
        ->assertSuccessful()
        ->assertSessionHas('current_ledger_id', $this->ledger->id);
});

test('dashboard is forbidden for non-owner', function () {
    $other = User::factory()->create();

    $this->actingAs($other)
        ->get(route('ledgers.dashboard', $this->ledger))
        ->assertForbidden();
});

test('cycle endpoint returns cycle dates', function () {
    $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/cycle")
        ->assertSuccessful()
        ->assertJsonStructure([
            'cycle_start',
            'cycle_end',
            'prev_cycle_start',
            'prev_cycle_end',
            'offset',
        ]);
});
