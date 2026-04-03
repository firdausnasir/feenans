<?php

use App\Actions\Bills\Queries\ListUpcomingBillsQuery;
use App\Actions\Budgets\Queries\ListBudgetsQuery;
use App\Actions\Dashboard\Queries\GetDashboardPageQuery;
use App\Data\Dashboard\Output\Web\DashboardPageData;
use App\Http\Controllers\Ledger\DashboardController;
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

test('dashboard page no longer exposes module props as deferred props', function () {
    $response = $this->actingAs($this->user)
        ->get(route('ledgers.dashboard', $this->ledger));

    $response
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('recentTransactions')
            ->missing('dailyTrend')
            ->missing('topCategories')
            ->missing('uncategorizedCount')
            ->missing('upcomingBills')
            ->missing('topBudgets')
        );

    expect(collect(data_get(
        json_decode(json_encode($response->viewData('page')), true),
        'deferredProps',
        [],
    ))->flatten()->all())->toBeEmpty();
});

test('dashboard shell does not expose upcoming bills props', function () {
    Bill::factory()->for($this->ledger)->for($this->account)->create([
        'next_due_date' => CarbonImmutable::today()->addDays(3)->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('ledgers.dashboard', $this->ledger));

    $response
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->missing('upcomingBills'));

    expect(collect(data_get(
        json_decode(json_encode($response->viewData('page')), true),
        'deferredProps',
        [],
    ))->flatten()->all())->not->toContain('upcomingBills');
});

test('dashboard shell does not load upcoming bills through ListUpcomingBillsQuery', function () {
    Bill::factory()->for($this->ledger)->for($this->account)->create([
        'name' => 'Water Bill',
        'amount' => 25.00,
        'next_due_date' => CarbonImmutable::today()->addDay()->toDateString(),
        'is_active' => true,
    ]);

    $called = false;
    $markCalled = function () use (&$called): void {
        $called = true;
    };
    $real = app()->make(ListUpcomingBillsQuery::class);

    app()->bind(ListUpcomingBillsQuery::class, function () use ($real, $markCalled) {
        return new class($real, $markCalled) extends ListUpcomingBillsQuery
        {
            public function __construct(
                private readonly ListUpcomingBillsQuery $real,
                private $markCalled,
            ) {}

            public function __invoke(Ledger $ledger, int $days = 3): array
            {
                ($this->markCalled)();

                return ($this->real)($ledger, $days);
            }
        };
    });

    $this->actingAs($this->user)
        ->get(route('ledgers.dashboard', $this->ledger))
        ->assertSuccessful();

    expect($called)->toBeFalse('Dashboard shell must not load upcoming bills through ListUpcomingBillsQuery');
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

test('dashboard shell does not expose top budgets props', function () {
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

    $response = $this->actingAs($this->user)
        ->get(route('ledgers.dashboard', $this->ledger));

    $response
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->missing('topBudgets'));

    expect(collect(data_get(
        json_decode(json_encode($response->viewData('page')), true),
        'deferredProps',
        [],
    ))->flatten()->all())->not->toContain('topBudgets');
});

test('GetDashboardPageQuery still builds dashboard module section callbacks', function () {
    $data = app(GetDashboardPageQuery::class)($this->ledger, 0);

    expect($data)->toBeInstanceOf(DashboardPageData::class)
        ->and($data->cycle)->toHaveKey('cycle_start')
        ->and($data->cycle)->toHaveKey('cycle_end')
        ->and($data->cycle)->toHaveKey('offset')
        ->and($data->summary)->toHaveKey('income')
        ->and($data->summary)->toHaveKey('expense')
        ->and($data->summary)->toHaveKey('net')
        ->and($data->accounts)->toBeArray()
        ->and($data->dailyTrend)->toBeCallable()
        ->and($data->topCategories)->toBeCallable()
        ->and($data->recentTransactions)->toBeCallable()
        ->and($data->uncategorizedCount)->toBeCallable()
        ->and($data->upcomingBills)->toBeCallable()
        ->and($data->topBudgets)->toBeCallable();
});

test('dashboard shell does not trigger upcoming bill or budget queries', function () {
    $this->mock(ListUpcomingBillsQuery::class)
        ->shouldNotReceive('__invoke');

    $this->mock(ListBudgetsQuery::class)
        ->shouldNotReceive('__invoke');

    $this->actingAs($this->user)
        ->get(route('ledgers.dashboard', $this->ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('upcomingBills')
            ->missing('topBudgets')
        );
});

test('previous cycle summary uses actual cycle bounds not naive day subtraction', function () {
    // Fix the date to April 15 2025: with cycle_start_day=31, the current cycle is
    // March 31–April 29 and the actual previous cycle is Feb 28–March 27.
    // The naive day-subtraction would compute March 1–March 30 (wrong).
    CarbonImmutable::setTestNow('2025-04-15');

    $ledger = Ledger::factory()->for($this->user)->create(['cycle_start_day' => 31]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    // Feb 28 is inside the actual prev cycle (Feb 28–March 27) but outside
    // the naive calculation (March 1–March 30), so this is the edge-case transaction.
    Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'transaction_type' => 'income',
        'amount' => '777.00',
        'transaction_date' => '2025-02-28',
    ]);

    // March 28 is inside the naive range but outside the actual prev cycle.
    // With the fix this must NOT appear in prev_income.
    Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'transaction_type' => 'income',
        'amount' => '999.00',
        'transaction_date' => '2025-03-28',
    ]);

    $this->actingAs($this->user)
        ->get(route('ledgers.dashboard', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.prev_income', 777)
        );

    CarbonImmutable::setTestNow();
});

test('DashboardController has no private composition methods', function () {
    $reflection = new ReflectionClass(DashboardController::class);
    $privateMethods = array_filter(
        $reflection->getMethods(ReflectionMethod::IS_PRIVATE),
        fn ($m) => $m->class === DashboardController::class,
    );

    expect($privateMethods)->toBeEmpty('DashboardController must delegate all composition to GetDashboardPageQuery');
});
