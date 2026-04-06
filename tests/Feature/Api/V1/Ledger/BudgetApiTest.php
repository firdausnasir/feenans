<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config()->set('app.paywall_enabled', true);
});

test('token authenticated premium client can list budgets for a ledger', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create([
        'name' => 'Food',
        'color' => '#22c55e',
    ]);

    $budget = Budget::query()->create([
        'ledger_id' => $ledger->id,
        'category_id' => $category->id,
        'amount' => 300,
        'period' => 'monthly',
        'start_date' => now()->toDateString(),
        'end_date' => null,
        'is_active' => true,
        'rollover' => false,
    ]);

    Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'transaction_type' => 'expense',
        'amount' => -75,
        'transaction_date' => now()->toDateString(),
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson("/api/v1/ledgers/{$ledger->id}/budgets");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $budget->id)
        ->assertJsonPath('data.0.category_id', $category->id)
        ->assertJsonPath('data.0.category_name', 'Food')
        ->assertJsonPath('data.0.category_color', '#22c55e')
        ->assertJsonPath('data.0.amount', 300.0)
        ->assertJsonPath('data.0.period', 'monthly')
        ->assertJsonPath('data.0.spent', 75.0)
        ->assertJsonPath('data.0.rollover', false)
        ->assertJsonPath('data.0.period_start', fn (mixed $value): bool => is_string($value) && $value !== '')
        ->assertJsonPath('data.0.period_end', fn (mixed $value): bool => is_string($value) && $value !== '')
        ->assertJsonPath('data.0.start_date', fn (mixed $value): bool => is_string($value) && $value !== '');
});

test('budget api dashboard top loader returns the top three budgets by usage', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $categories = collect([
        ['name' => 'Food', 'amount' => 100, 'spent' => -90],
        ['name' => 'Travel', 'amount' => 100, 'spent' => -80],
        ['name' => 'Bills', 'amount' => 100, 'spent' => -70],
        ['name' => 'Fun', 'amount' => 100, 'spent' => -60],
    ])->map(function (array $definition) use ($ledger, $account) {
        $category = Category::factory()->for($ledger)->create([
            'name' => $definition['name'],
        ]);

        Budget::factory()->for($ledger)->create([
            'category_id' => $category->id,
            'amount' => $definition['amount'],
            'period' => 'monthly',
            'start_date' => now()->startOfMonth()->toDateString(),
            'is_active' => true,
        ]);

        Transaction::factory()->for($ledger)->for($account)->for($category)->expense()->create([
            'amount' => $definition['spent'],
            'transaction_date' => now()->toDateString(),
        ]);

        return $category;
    });

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson(route('api.v1.ledgers.budgets.dashboard-top', $ledger));

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.category_name', $categories[0]->name)
        ->assertJsonPath('data.0.percentage', 90.0)
        ->assertJsonPath('data.1.category_name', $categories[1]->name)
        ->assertJsonPath('data.1.percentage', 80.0)
        ->assertJsonPath('data.2.category_name', $categories[2]->name)
        ->assertJsonPath('data.2.percentage', 70.0);
});

test('budget api create returns validation errors as json', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson("/api/v1/ledgers/{$ledger->id}/budgets", [
        'amount' => 0,
        'period' => '',
        'start_date' => '',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['amount', 'period', 'start_date'])
        ->assertJsonPath('errors.amount.0', 'The budget amount must be at least 0.01.')
        ->assertJsonPath('errors.period.0', 'Please select a budget period.')
        ->assertJsonPath('errors.start_date.0', 'Please select a start date.');
});

test('budget api create returns created budget contract', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create([
        'name' => 'Travel',
        'color' => '#22c55e',
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson("/api/v1/ledgers/{$ledger->id}/budgets", [
        'category_id' => $category->id,
        'amount' => 250,
        'period' => 'monthly',
        'start_date' => now()->toDateString(),
        'end_date' => null,
        'rollover' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.category_id', $category->id)
        ->assertJsonPath('data.category_name', 'Travel')
        ->assertJsonPath('data.category_color', '#22c55e')
        ->assertJsonPath('data.amount', 250.0)
        ->assertJsonPath('data.period', 'monthly')
        ->assertJsonPath('data.rollover', true)
        ->assertJsonPath('data.spent', 0.0)
        ->assertJsonPath('data.remaining', 250.0);
});

test('budget api update returns updated budget json', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $originalCategory = Category::factory()->for($ledger)->create();
    $updatedCategory = Category::factory()->for($ledger)->create([
        'name' => 'Home',
        'color' => '#ef4444',
    ]);

    $budget = Budget::query()->create([
        'ledger_id' => $ledger->id,
        'category_id' => $originalCategory->id,
        'amount' => 100,
        'period' => 'monthly',
        'start_date' => now()->toDateString(),
        'end_date' => null,
        'is_active' => true,
        'rollover' => false,
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->patchJson("/api/v1/ledgers/{$ledger->id}/budgets/{$budget->id}", [
        'category_id' => $updatedCategory->id,
        'amount' => 180,
        'period' => 'yearly',
        'start_date' => now()->toDateString(),
        'end_date' => null,
        'rollover' => true,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $budget->id)
        ->assertJsonPath('data.category_id', $updatedCategory->id)
        ->assertJsonPath('data.category_name', 'Home')
        ->assertJsonPath('data.category_color', '#ef4444')
        ->assertJsonPath('data.amount', 180.0)
        ->assertJsonPath('data.period', 'yearly')
        ->assertJsonPath('data.rollover', true);
});

test('budget api delete returns deleted budget json', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create(['name' => 'Bills']);

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

    Sanctum::actingAs($user, ['*']);

    $response = $this->deleteJson("/api/v1/ledgers/{$ledger->id}/budgets/{$budget->id}");

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $budget->id)
        ->assertJsonPath('data.category_name', 'Bills');

    expect(Budget::query()->whereKey($budget->id)->exists())->toBeFalse();
});

test('budget api create rejects categories from another ledger', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $foreignLedger = Ledger::factory()->create();
    $foreignCategory = Category::factory()->for($foreignLedger)->create();

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson("/api/v1/ledgers/{$ledger->id}/budgets", [
        'category_id' => $foreignCategory->id,
        'amount' => 250,
        'period' => 'monthly',
        'start_date' => now()->toDateString(),
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['category_id']);
});

test('budget api returns json forbidden when ledger policy denies access', function () {
    $owner = User::factory()->create();
    $owner->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $outsider = User::factory()->create();
    $outsider->membership()->update(['tier' => 'premium', 'status' => 'active']);
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

    Sanctum::actingAs($outsider, ['*']);

    $this->getJson("/api/v1/ledgers/{$ledger->id}/budgets")
        ->assertForbidden();

    $this->postJson("/api/v1/ledgers/{$ledger->id}/budgets", [
        'category_id' => $category->id,
        'amount' => 100,
        'period' => 'monthly',
        'start_date' => now()->toDateString(),
    ])->assertForbidden();

    $this->patchJson("/api/v1/ledgers/{$ledger->id}/budgets/{$budget->id}", [
        'category_id' => $category->id,
        'amount' => 150,
        'period' => 'monthly',
        'start_date' => now()->toDateString(),
    ])->assertForbidden();

    $this->deleteJson("/api/v1/ledgers/{$ledger->id}/budgets/{$budget->id}")
        ->assertForbidden();
});

test('budget api rejects guest requests', function () {
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

    $this->getJson("/api/v1/ledgers/{$ledger->id}/budgets")
        ->assertUnauthorized();

    $this->postJson("/api/v1/ledgers/{$ledger->id}/budgets", [
        'category_id' => $category->id,
        'amount' => 100,
        'period' => 'monthly',
        'start_date' => now()->toDateString(),
    ])->assertUnauthorized();

    $this->patchJson("/api/v1/ledgers/{$ledger->id}/budgets/{$budget->id}", [
        'category_id' => $category->id,
        'amount' => 150,
        'period' => 'monthly',
        'start_date' => now()->toDateString(),
    ])->assertUnauthorized();

    $this->deleteJson("/api/v1/ledgers/{$ledger->id}/budgets/{$budget->id}")
        ->assertUnauthorized();
});

test('free token authenticated client cannot access budget api', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    Sanctum::actingAs($user, ['*']);

    $this->getJson("/api/v1/ledgers/{$ledger->id}/budgets")
        ->assertForbidden();
});
