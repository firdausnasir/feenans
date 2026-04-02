<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('budget index page renders successfully', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create(['name' => 'Food']);

    Budget::query()->create([
        'ledger_id' => $ledger->id,
        'category_id' => $category->id,
        'amount' => 300,
        'period' => 'monthly',
        'start_date' => now()->toDateString(),
        'end_date' => null,
        'is_active' => true,
        'rollover' => false,
    ]);

    $response = $this->actingAs($user)
        ->get(route('ledgers.budgets.index', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/budgets/index')
            ->has('categories', 1, fn (Assert $categoryPage) => $categoryPage
                ->where('name', 'Food')
                ->etc()
            )
            ->missing('budgets')
        );

    $response->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->has('budgets', 1, fn (Assert $budgetPage) => $budgetPage
                ->where('category_name', 'Food')
                ->etc()
            )
        )
    );
});

test('budget index keeps categories immediate and hierarchical while budgets stay deferred', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $parent = Category::factory()->for($ledger)->create([
        'name' => 'Food',
        'position' => 1,
    ]);
    $child = Category::factory()->for($ledger)->create([
        'parent_id' => $parent->id,
        'name' => 'Dining Out',
        'position' => 1,
    ]);

    Budget::query()->create([
        'ledger_id' => $ledger->id,
        'category_id' => $child->id,
        'amount' => 300,
        'period' => 'monthly',
        'start_date' => now()->toDateString(),
        'end_date' => null,
        'is_active' => true,
        'rollover' => false,
    ]);

    $response = $this->actingAs($user)
        ->get(route('ledgers.budgets.index', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/budgets/index')
            ->has('categories', 1, fn (Assert $categoryPage) => $categoryPage
                ->where('name', 'Food')
                ->has('children', 1, fn (Assert $childPage) => $childPage
                    ->where('name', 'Dining Out')
                    ->etc()
                )
                ->etc()
            )
            ->missing('budgets')
        );

    $response->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->has('budgets', 1, fn (Assert $budgetPage) => $budgetPage
                ->where('category_id', $child->id)
                ->where('category_name', 'Dining Out')
                ->etc()
            )
        )
    );
});

test('budget can be created through web routes', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create();

    $response = $this->actingAs($user)
        ->from(route('ledgers.budgets.index', $ledger))
        ->post(route('ledgers.budgets.store', $ledger), [
            'category_id' => $category->id,
            'amount' => 250,
            'period' => 'monthly',
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'rollover' => false,
        ]);

    $response->assertRedirect(route('ledgers.budgets.index', $ledger))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Budget created.');

    expect($ledger->budgets()->where('category_id', $category->id)->exists())->toBeTrue();
});

test('budget can be updated through web routes', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
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

    $response = $this->actingAs($user)
        ->from(route('ledgers.budgets.index', $ledger))
        ->put(route('ledgers.budgets.update', [$ledger, $budget]), [
            'category_id' => $category->id,
            'amount' => 175,
            'period' => 'monthly',
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'rollover' => true,
        ]);

    $response->assertRedirect(route('ledgers.budgets.index', $ledger))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Budget updated.');

    expect($budget->fresh())
        ->amount->toBe('175.00')
        ->rollover->toBeTrue();
});

test('budget can be deleted through web routes', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
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

    $response = $this->actingAs($user)
        ->from(route('ledgers.budgets.index', $ledger))
        ->delete(route('ledgers.budgets.destroy', [$ledger, $budget]));

    $response->assertRedirect(route('ledgers.budgets.index', $ledger))
        ->assertSessionHas('success', 'Budget deleted.');

    expect(Budget::query()->whereKey($budget->id)->exists())->toBeFalse();
});

test('budget store rejects categories from another ledger', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $foreignLedger = Ledger::factory()->create();
    $foreignCategory = Category::factory()->for($foreignLedger)->create();

    $response = $this->actingAs($user)
        ->from(route('ledgers.budgets.index', $ledger))
        ->post(route('ledgers.budgets.store', $ledger), [
            'category_id' => $foreignCategory->id,
            'amount' => 250,
            'period' => 'monthly',
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'rollover' => false,
        ]);

    $response->assertRedirect(route('ledgers.budgets.index', $ledger))
        ->assertSessionHasErrors('category_id');

    expect($ledger->budgets()->count())->toBe(0);
});

test('budget store preserves validation messages through web routes', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->from(route('ledgers.budgets.index', $ledger))
        ->post(route('ledgers.budgets.store', $ledger), [
            'amount' => 0,
            'period' => '',
            'start_date' => '',
            'end_date' => now()->subDay()->toDateString(),
            'rollover' => false,
        ]);

    $response->assertRedirect(route('ledgers.budgets.index', $ledger))
        ->assertSessionHasErrors([
            'amount' => 'The budget amount must be at least 0.01.',
            'period' => 'Please select a budget period.',
            'start_date' => 'Please select a start date.',
        ]);

    expect($ledger->budgets()->count())->toBe(0);
});

test('budget update is forbidden for another users ledger', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $intruder->membership()->update(['tier' => 'premium', 'status' => 'active']);
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
    $intruder->membership()->update(['tier' => 'premium', 'status' => 'active']);
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
