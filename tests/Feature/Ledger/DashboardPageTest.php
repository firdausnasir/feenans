<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->ledger = Ledger::factory()->for($this->user)->create(['cycle_start_day' => 1]);
    $this->accountType = AccountType::factory()->for($this->ledger)->create();
    $this->account = Account::factory()->for($this->ledger)->for($this->accountType)->create();
    $this->category = Category::factory()->for($this->ledger)->create();
    $this->token = $this->user->createToken('test');
});

test('dashboard page loads successfully', function () {
    $this->actingAs($this->user)
        ->get(route('ledgers.dashboard', $this->ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/dashboard')
        );
});

test('summary income and expense calculated correctly for current cycle', function () {
    $now = CarbonImmutable::now();
    ['start' => $start, 'end' => $end] = $this->ledger->cycleBounds($now);

    // In-cycle income
    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($this->category)
        ->create([
            'transaction_type' => 'income',
            'amount' => '100.00',
            'transaction_date' => $start->addDays(1)->toDateString(),
        ]);

    // In-cycle expense
    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($this->category)
        ->create([
            'transaction_type' => 'expense',
            'amount' => '-40.00',
            'transaction_date' => $start->addDays(2)->toDateString(),
        ]);

    $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/transactions/summary?date_from={$start->toDateString()}&date_to={$end->toDateString()}")
        ->assertSuccessful()
        ->assertJson([
            'income' => 100.0,
            'expense' => -40.0,
            'net' => 60.0,
        ]);
});

test('summary excludes transactions outside current cycle', function () {
    $now = CarbonImmutable::now();
    ['start' => $start, 'end' => $end] = $this->ledger->cycleBounds($now);

    // In-cycle income
    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($this->category)
        ->create([
            'transaction_type' => 'income',
            'amount' => '200.00',
            'transaction_date' => $start->addDays(1)->toDateString(),
        ]);

    // Out-of-cycle transaction (previous cycle)
    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($this->category)
        ->create([
            'transaction_type' => 'income',
            'amount' => '999.00',
            'transaction_date' => $start->subMonths(2)->toDateString(),
        ]);

    $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/transactions/summary?date_from={$start->toDateString()}&date_to={$end->toDateString()}")
        ->assertSuccessful()
        ->assertJson([
            'income' => 200.0,
            'expense' => 0.0,
            'net' => 200.0,
        ]);
});

test('accounts grouped by type with correct balances', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $token = $user->createToken('test');

    $typeA = AccountType::factory()->for($ledger)->create(['name' => 'Checking', 'position' => 1]);
    $typeB = AccountType::factory()->for($ledger)->create(['name' => 'Credit', 'position' => 2]);

    $accountA = Account::factory()->for($ledger)->for($typeA)->create(['initial_balance' => '100.00', 'name' => 'Account A']);
    $accountB = Account::factory()->for($ledger)->for($typeB)->create(['initial_balance' => '0.00', 'name' => 'Account B']);

    $category = Category::factory()->for($ledger)->create();

    // Add a transaction to account A
    Transaction::factory()
        ->for($ledger)
        ->for($accountA)
        ->for($category)
        ->create(['amount' => '50.00']);

    $response = $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$ledger->id}/accounts?grouped=true")
        ->assertSuccessful();

    $data = $response->json('data');

    expect($data)->toHaveCount(2);
    expect($data[0]['type']['name'])->toBe('Checking');
    expect($data[0]['accounts'][0]['name'])->toBe('Account A');
    expect((float) $data[0]['accounts'][0]['current_balance'])->toBe(150.0);
    expect($data[1]['type']['name'])->toBe('Credit');
    expect($data[1]['accounts'][0]['name'])->toBe('Account B');
    expect((float) $data[1]['accounts'][0]['current_balance'])->toBe(0.0);
});

test('upcoming bills are returned', function () {
    Bill::factory()->for($this->ledger)->for($this->account)->create([
        'next_due_date' => CarbonImmutable::today()->addDays(3)->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/bills?upcoming=true")
        ->assertSuccessful();

    $json = $response->json();
    expect($json)->toHaveKey('upcoming');
    expect($json)->toHaveKey('due');
    expect($json)->toHaveKey('missed');
    expect($json['upcoming'])->toHaveCount(1);
    expect($json['due'])->toHaveCount(0);
    expect($json['missed'])->toHaveCount(0);
});

test('recent transactions endpoint returns paginated results', function () {
    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($this->category)
        ->count(15)
        ->create(['transaction_date' => CarbonImmutable::today()->toDateString()]);

    $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/transactions?per_page=10")
        ->assertSuccessful()
        ->assertJsonCount(10, 'data');
});

test('cycle navigation scopes summary to the selected cycle', function () {
    $now = CarbonImmutable::now();
    ['start' => $currentStart] = $this->ledger->cycleBounds($now);
    ['start' => $prevStart, 'end' => $prevEnd] = $this->ledger->cycleBounds($now->subMonthNoOverflow());

    // Current-cycle transaction
    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($this->category)
        ->create([
            'transaction_type' => 'income',
            'amount' => '500.00',
            'transaction_date' => $currentStart->addDays(1)->toDateString(),
            'description' => 'Current cycle income',
        ]);

    // Previous-cycle transactions
    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($this->category)
        ->create([
            'transaction_type' => 'income',
            'amount' => '200.00',
            'transaction_date' => $prevStart->addDays(1)->toDateString(),
            'description' => 'Previous cycle income',
        ]);

    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($this->category)
        ->create([
            'transaction_type' => 'expense',
            'amount' => '-75.00',
            'transaction_date' => $prevStart->addDays(2)->toDateString(),
            'description' => 'Previous cycle expense',
        ]);

    // Verify summary scoped to previous cycle
    $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/transactions/summary?date_from={$prevStart->toDateString()}&date_to={$prevEnd->toDateString()}")
        ->assertSuccessful()
        ->assertJson([
            'income' => 200.0,
            'expense' => -75.0,
            'net' => 125.0,
        ]);

    // Verify cycle dates for offset -1
    $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/cycle?offset=-1")
        ->assertSuccessful()
        ->assertJson([
            'cycle_start' => $prevStart->toDateString(),
            'cycle_end' => $prevEnd->toDateString(),
            'offset' => -1,
        ]);

    // Verify top spending scoped to previous cycle
    $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/categories/top-spending?date_from={$prevStart->toDateString()}&date_to={$prevEnd->toDateString()}")
        ->assertSuccessful()
        ->assertJsonFragment([
            'total' => 75.0,
        ]);
});

test('account balances are not affected by cycle scope', function () {
    $this->account->update(['initial_balance' => '1000.00']);

    $now = CarbonImmutable::now();
    ['start' => $currentStart] = $this->ledger->cycleBounds($now);

    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($this->category)
        ->create([
            'transaction_type' => 'income',
            'amount' => '250.00',
            'transaction_date' => $currentStart->addDays(1)->toDateString(),
        ]);

    // Fetch accounts — balance should always include all transactions
    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/accounts?grouped=true")
        ->assertSuccessful();

    $balance = (float) $response->json('data.0.accounts.0.current_balance');

    expect($balance)->toBe(1250.0);
});
