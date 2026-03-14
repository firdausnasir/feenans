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

test('dashboard supports cycle offset navigation to previous month', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $now = CarbonImmutable::now();
    $lastMonth = $now->subMonth();
    ['start' => $lastStart] = $ledger->cycleBounds($lastMonth);

    Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-99.00',
        'transaction_date' => $lastStart->addDay()->toDateString(),
    ]);

    $this->actingAs($user)
        ->get(route('ledgers.dashboard', ['ledger' => $ledger, 'cycle_offset' => -1]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/dashboard')
            ->where('cycleOffset', -1)
            ->where('summary.expense', fn ($v) => (float) $v === 99.0)
        );
});

test('dashboard returns daily expense trend data', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $now = CarbonImmutable::now();
    ['start' => $start] = $ledger->cycleBounds($now);

    Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-20.00',
        'transaction_date' => $start->addDay()->toDateString(),
    ]);

    $this->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('dailyExpenseTrend')
        );
});

test('dashboard returns top categories', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create(['name' => 'Food']);

    $now = CarbonImmutable::now();
    ['start' => $start] = $ledger->cycleBounds($now);

    Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-50.00',
        'transaction_date' => $start->addDay()->toDateString(),
    ]);

    $this->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('topCategories', 1)
            ->where('topCategories.0.name', 'Food')
            ->where('topCategories.0.total', fn ($v) => (float) $v === 50.0)
        );
});

test('dashboard stores current ledger id in session', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger))
        ->assertSuccessful()
        ->assertSessionHas('current_ledger_id', $ledger->id);
});

test('dashboard is forbidden for non-owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();

    $this->actingAs($other)
        ->get(route('ledgers.dashboard', $ledger))
        ->assertForbidden();
});

test('dashboard returns cycle dates', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);

    $this->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('cycleDates.start')
            ->has('cycleDates.end')
        );
});
