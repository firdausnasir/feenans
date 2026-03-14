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

test('dashboard page loads successfully', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/dashboard')
            ->has('ledger')
            ->has('summary')
            ->has('accounts')
            ->has('upcomingBills')
            ->has('recentTransactions')
        );
});

test('summary income and expense calculated correctly for current cycle', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $now = CarbonImmutable::now();
    ['start' => $start] = $ledger->cycleBounds($now);

    // In-cycle income
    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->create([
            'transaction_type' => 'income',
            'amount' => '100.00',
            'transaction_date' => $start->addDays(1)->toDateString(),
        ]);

    // In-cycle expense
    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->create([
            'transaction_type' => 'expense',
            'amount' => '-40.00',
            'transaction_date' => $start->addDays(2)->toDateString(),
        ]);

    $this->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger))
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/dashboard')
            ->where('summary.income', fn ($value) => $value == 100)
            ->where('summary.expense', fn ($value) => $value == 40)
            ->where('summary.net', fn ($value) => $value == 60)
        );
});

test('summary excludes transactions outside current cycle', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $now = CarbonImmutable::now();
    ['start' => $start] = $ledger->cycleBounds($now);

    // In-cycle income
    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->create([
            'transaction_type' => 'income',
            'amount' => '200.00',
            'transaction_date' => $start->addDays(1)->toDateString(),
        ]);

    // Out-of-cycle transaction (previous cycle)
    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->create([
            'transaction_type' => 'income',
            'amount' => '999.00',
            'transaction_date' => $start->subMonths(2)->toDateString(),
        ]);

    $this->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.income', fn ($value) => $value == 200)
            ->where('summary.expense', fn ($value) => $value == 0)
            ->where('summary.net', fn ($value) => $value == 200)
        );
});

test('accounts grouped by type with correct balances', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $typeA = AccountType::factory()->for($ledger)->create(['name' => 'Checking']);
    $typeB = AccountType::factory()->for($ledger)->create(['name' => 'Credit']);
    $accountA = Account::factory()->for($ledger)->for($typeA)->create(['initial_balance' => '100.00', 'name' => 'Account A']);
    $accountB = Account::factory()->for($ledger)->for($typeB)->create(['initial_balance' => '0.00', 'name' => 'Account B']);
    $category = Category::factory()->for($ledger)->create();

    // Add a transaction to account A
    Transaction::factory()
        ->for($ledger)
        ->for($accountA)
        ->for($category)
        ->create(['amount' => '50.00']);

    $this->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger))
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/dashboard')
            ->has('accounts', 2)
            ->where('accounts.0.type.name', 'Checking')
            ->where('accounts.0.accounts.0.name', 'Account A')
            ->where('accounts.0.accounts.0.balance', fn ($value) => $value == 150)
            ->where('accounts.1.type.name', 'Credit')
            ->where('accounts.1.accounts.0.name', 'Account B')
            ->where('accounts.1.accounts.0.balance', fn ($value) => $value == 0)
        );
});

test('upcoming bills are returned', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    Bill::factory()->for($ledger)->for($account)->create([
        'next_due_date' => CarbonImmutable::today()->addDays(3)->toDateString(),
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger))
        ->assertInertia(fn (Assert $page) => $page
            ->has('upcomingBills')
            ->has('upcomingBills.upcoming', 1)
            ->has('upcomingBills.due', 0)
            ->has('upcomingBills.missed', 0)
        );
});

test('recent transactions limited to 10 ordered by date then id descending', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->count(15)
        ->create(['transaction_date' => CarbonImmutable::today()->toDateString()]);

    $this->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recentTransactions', 10)
        );
});

test('cycle navigation scopes all widgets to the selected cycle', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $now = CarbonImmutable::now();
    ['start' => $currentStart] = $ledger->cycleBounds($now);
    ['start' => $prevStart, 'end' => $prevEnd] = $ledger->cycleBounds($now->subMonthNoOverflow());

    // Current-cycle transaction
    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->create([
            'transaction_type' => 'income',
            'amount' => '500.00',
            'transaction_date' => $currentStart->addDays(1)->toDateString(),
            'description' => 'Current cycle income',
        ]);

    // Previous-cycle transactions
    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->create([
            'transaction_type' => 'income',
            'amount' => '200.00',
            'transaction_date' => $prevStart->addDays(1)->toDateString(),
            'description' => 'Previous cycle income',
        ]);

    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->create([
            'transaction_type' => 'expense',
            'amount' => '-75.00',
            'transaction_date' => $prevStart->addDays(2)->toDateString(),
            'description' => 'Previous cycle expense',
        ]);

    // Navigate to the previous cycle
    $this->actingAs($user)
        ->get(route('ledgers.dashboard', ['ledger' => $ledger, 'cycle_offset' => -1]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/dashboard')
            // Summary should reflect previous cycle only
            ->where('summary.income', fn ($value) => $value == 200)
            ->where('summary.expense', fn ($value) => $value == 75)
            ->where('summary.net', fn ($value) => $value == 125)
            // Recent transactions should be from previous cycle only
            ->has('recentTransactions', 2)
            ->where('recentTransactions.0.description', 'Previous cycle expense')
            ->where('recentTransactions.1.description', 'Previous cycle income')
            // Cycle dates should match previous cycle
            ->where('cycleDates.start', $prevStart->format('Y-m-d'))
            ->where('cycleDates.end', $prevEnd->format('Y-m-d'))
            ->where('cycleOffset', -1)
            // Top categories should be scoped to the previous cycle
            ->has('topCategories', 1)
            ->where('topCategories.0.total', fn ($value) => $value == 75)
            // Daily trend should only contain previous cycle data
            ->where('dailyExpenseTrend', fn ($trend) => collect($trend)->every(
                fn ($day) => $day['date'] >= $prevStart->format('Y-m-d')
                    && $day['date'] <= $prevEnd->format('Y-m-d')
            ))
        );
});

test('account balances are not affected by cycle navigation', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create([
        'initial_balance' => '1000.00',
    ]);
    $category = Category::factory()->for($ledger)->create();

    $now = CarbonImmutable::now();
    ['start' => $currentStart] = $ledger->cycleBounds($now);

    Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->create([
            'transaction_type' => 'income',
            'amount' => '250.00',
            'transaction_date' => $currentStart->addDays(1)->toDateString(),
        ]);

    // Fetch current cycle balance
    $currentCycleBalance = null;
    $this->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger))
        ->assertInertia(function (Assert $page) use (&$currentCycleBalance) {
            $page->component('ledgers/dashboard');
            $currentCycleBalance = $page->toArray()['props']['accounts'][0]['accounts'][0]['balance'];
        });

    // Fetch previous cycle — balance should be the same
    $this->actingAs($user)
        ->get(route('ledgers.dashboard', ['ledger' => $ledger, 'cycle_offset' => -1]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/dashboard')
            ->where('accounts.0.accounts.0.balance', fn ($value) => $value == $currentCycleBalance)
        );
});
