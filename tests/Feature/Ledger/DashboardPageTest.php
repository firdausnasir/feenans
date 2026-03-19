<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bill;
use App\Models\Budget;
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
});

test('dashboard page loads with cycle, summary, and accounts props', function () {
    $this->actingAs($this->user)
        ->get(route('ledgers.dashboard', $this->ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/dashboard')
            ->has('cycle', fn (Assert $cycle) => $cycle
                ->has('cycle_start')
                ->has('cycle_end')
                ->has('prev_cycle_start')
                ->has('prev_cycle_end')
                ->where('offset', 0)
            )
            ->has('summary', fn (Assert $summary) => $summary
                ->has('income')
                ->has('expense')
                ->has('net')
                ->has('prev_income')
                ->has('prev_expense')
            )
            ->has('accounts')
        );
});

test('summary income and expense calculated correctly for current cycle', function () {
    $now = CarbonImmutable::now();
    ['start' => $start, 'end' => $end] = $this->ledger->cycleBounds($now);

    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($this->category)
        ->create([
            'transaction_type' => 'income',
            'amount' => '100.00',
            'transaction_date' => $start->addDays(1)->toDateString(),
        ]);

    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($this->category)
        ->create([
            'transaction_type' => 'expense',
            'amount' => '-40.00',
            'transaction_date' => $start->addDays(2)->toDateString(),
        ]);

    $this->actingAs($this->user)
        ->get(route('ledgers.dashboard', $this->ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.income', 100)
            ->where('summary.expense', -40)
            ->where('summary.net', 60)
        );
});

test('summary excludes transactions outside current cycle', function () {
    $now = CarbonImmutable::now();
    ['start' => $start] = $this->ledger->cycleBounds($now);

    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($this->category)
        ->create([
            'transaction_type' => 'income',
            'amount' => '200.00',
            'transaction_date' => $start->addDays(1)->toDateString(),
        ]);

    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($this->category)
        ->create([
            'transaction_type' => 'income',
            'amount' => '999.00',
            'transaction_date' => $start->subMonths(2)->toDateString(),
        ]);

    $this->actingAs($this->user)
        ->get(route('ledgers.dashboard', $this->ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.income', 200)
            ->where('summary.expense', 0)
            ->where('summary.net', 200)
        );
});

test('accounts grouped by type with correct balances', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $typeA = AccountType::factory()->for($ledger)->create(['name' => 'Checking', 'position' => 1]);
    $typeB = AccountType::factory()->for($ledger)->create(['name' => 'Credit', 'position' => 2]);

    $accountA = Account::factory()->for($ledger)->for($typeA)->create(['initial_balance' => '100.00', 'name' => 'Account A']);
    Account::factory()->for($ledger)->for($typeB)->create(['initial_balance' => '0.00', 'name' => 'Account B']);

    $category = Category::factory()->for($ledger)->create();

    Transaction::factory()
        ->for($ledger)
        ->for($accountA)
        ->for($category)
        ->create(['amount' => '50.00']);

    $this->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('accounts', 2)
            ->where('accounts.0.type.name', 'Checking')
            ->where('accounts.0.accounts.0.name', 'Account A')
            ->where('accounts.1.type.name', 'Credit')
            ->where('accounts.1.accounts.0.name', 'Account B')
        );
});

test('deferred props are available via follow-up request', function () {
    $now = CarbonImmutable::now();
    ['start' => $start] = $this->ledger->cycleBounds($now);

    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($this->category)
        ->count(5)
        ->create([
            'transaction_type' => 'expense',
            'amount' => '-20.00',
            'transaction_date' => $start->addDay()->toDateString(),
        ]);

    $this->actingAs($this->user)
        ->get(route('ledgers.dashboard', $this->ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('recentTransactions')
            ->missing('dailyTrend')
            ->missing('topCategories')
            ->missing('uncategorizedCount')
            ->missing('upcomingBills')
            ->missing('topBudgets')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('recentTransactions', 5)
                ->has('uncategorizedCount')
                ->has('dailyTrend')
                ->has('topCategories')
                ->has('upcomingBills')
                ->has('topBudgets')
            )
        );
});

test('upcoming bills are returned in deferred props', function () {
    Bill::factory()->for($this->ledger)->for($this->account)->create([
        'next_due_date' => CarbonImmutable::today()->addDays(3)->toDateString(),
        'is_active' => true,
    ]);

    $this->actingAs($this->user)
        ->get(route('ledgers.dashboard', $this->ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('upcomingBills.upcoming', 1)
                ->has('upcomingBills.due', 0)
                ->has('upcomingBills.missed', 0)
            )
        );
});

test('cycle navigation scopes summary to the selected cycle', function () {
    $now = CarbonImmutable::now();
    ['start' => $prevStart] = $this->ledger->cycleBounds($now->subMonthNoOverflow());

    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($this->category)
        ->create([
            'transaction_type' => 'income',
            'amount' => '200.00',
            'transaction_date' => $prevStart->addDays(1)->toDateString(),
        ]);

    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($this->category)
        ->create([
            'transaction_type' => 'expense',
            'amount' => '-75.00',
            'transaction_date' => $prevStart->addDays(2)->toDateString(),
        ]);

    $this->actingAs($this->user)
        ->get(route('ledgers.dashboard', [$this->ledger, 'offset' => -1]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('cycle.offset', -1)
            ->where('summary.income', 200)
            ->where('summary.expense', -75)
            ->where('summary.net', 125)
        );
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

    $this->actingAs($this->user)
        ->get(route('ledgers.dashboard', $this->ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('accounts.0.accounts.0.current_balance', '1250.00')
        );
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

test('top budgets are returned sorted by percentage', function () {
    $catA = Category::factory()->for($this->ledger)->create(['name' => 'Food']);
    $catB = Category::factory()->for($this->ledger)->create(['name' => 'Transport']);

    $now = CarbonImmutable::now();
    ['start' => $start] = $this->ledger->cycleBounds($now);

    Budget::factory()->for($this->ledger)->create([
        'category_id' => $catA->id,
        'amount' => 100,
        'period' => 'monthly',
        'start_date' => $start->toDateString(),
        'is_active' => true,
    ]);

    Budget::factory()->for($this->ledger)->create([
        'category_id' => $catB->id,
        'amount' => 50,
        'period' => 'monthly',
        'start_date' => $start->toDateString(),
        'is_active' => true,
    ]);

    Transaction::factory()->for($this->ledger)->for($this->account)->for($catA)->create([
        'transaction_type' => 'expense',
        'amount' => '-80.00',
        'transaction_date' => $start->addDay()->toDateString(),
    ]);

    Transaction::factory()->for($this->ledger)->for($this->account)->for($catB)->create([
        'transaction_type' => 'expense',
        'amount' => '-45.00',
        'transaction_date' => $start->addDay()->toDateString(),
    ]);

    $this->actingAs($this->user)
        ->get(route('ledgers.dashboard', $this->ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('topBudgets', 2)
                ->where('topBudgets.0.category_name', 'Transport')
                ->where('topBudgets.1.category_name', 'Food')
            )
        );
});
