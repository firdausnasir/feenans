<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('budget index page renders successfully', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.budgets.index', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('ledgers/budgets/index')
        );
});

test('budget store rejects categories from another ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $foreignLedger = Ledger::factory()->create();
    $foreignCategory = Category::factory()->for($foreignLedger)->create();

    $response = $this->actingAs($user)
        ->post(route('ledgers.budgets.store', $ledger), [
            'category_id' => $foreignCategory->id,
            'amount' => 250,
            'period' => 'monthly',
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'rollover' => false,
        ]);

    $response->assertSessionHasErrors('category_id');

    expect($ledger->budgets()->count())->toBe(0);
});

test('budget update is forbidden for another users ledger', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();
    $category = Category::factory()->for($ledger)->create();

    $budget = Budget::query()->create([
        'ledger_id' => $ledger->id,
        'category_id' => $category->id,
        'amount' => 100,
        'period' => 'monthly',
        'start_date' => now()->toDateString(),
        'end_date' => null,
        'is_active' => true,
        'rollover' => false,
    ]);

    $this->actingAs($intruder)
        ->put(route('ledgers.budgets.update', [$ledger, $budget]), [
            'category_id' => $category->id,
            'amount' => 150,
            'period' => 'monthly',
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'rollover' => false,
        ])
        ->assertForbidden();
});

test('budget destroy is forbidden for another users ledger', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();
    $category = Category::factory()->for($ledger)->create();

    $budget = Budget::query()->create([
        'ledger_id' => $ledger->id,
        'category_id' => $category->id,
        'amount' => 100,
        'period' => 'monthly',
        'start_date' => now()->toDateString(),
        'end_date' => null,
        'is_active' => true,
        'rollover' => false,
    ]);

    $this->actingAs($intruder)
        ->delete(route('ledgers.budgets.destroy', [$ledger, $budget]))
        ->assertForbidden();

    expect(Budget::query()->whereKey($budget->id)->exists())->toBeTrue();
});

test('dashboard avoids repeated category lookups for top categories', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $categories = Category::factory()->for($ledger)->count(5)->create();

    foreach ($categories as $index => $category) {
        Transaction::factory()
            ->for($ledger)
            ->for($account)
            ->for($category)
            ->create([
                'transaction_type' => 'expense',
                'amount' => -(10 + $index),
                'transaction_date' => now()->toDateString(),
            ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($user)->get(route('ledgers.dashboard', $ledger))->assertSuccessful();

    $perCategoryLookupQueries = collect(DB::getQueryLog())
        ->filter(function (array $query): bool {
            $sql = strtolower($query['query']);

            return str_contains($sql, 'from "categories"')
                && str_contains($sql, '"categories"."ledger_id" = ?')
                && str_contains($sql, '"categories"."id" = ?')
                && ! str_contains($sql, ' in (');
        });

    expect($perCategoryLookupQueries)->toHaveCount(0);

    DB::disableQueryLog();
});
