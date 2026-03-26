<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\User;

test('free user isPremium returns false', function () {
    $user = User::factory()->create();

    expect($user->isPremium())->toBeFalse();
});

test('premium user isPremium returns true', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);

    expect($user->fresh()->isPremium())->toBeTrue();
});

test('trialing user isPremium returns true', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'trialing']);

    expect($user->fresh()->isPremium())->toBeTrue();
});

test('canceled premium user isPremium returns false', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'canceled']);

    expect($user->fresh()->isPremium())->toBeFalse();
});

test('shared props include membership data for free user', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.membership.tier', 'free')
            ->where('auth.user.membership.is_premium', false)
        );
});

test('shared props include membership data for premium user', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.membership.tier', 'premium')
            ->where('auth.user.membership.is_premium', true)
        );
});

test('free user is redirected from reports to premium page', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger))
        ->assertRedirect(route('premium'));
});

test('premium user can access reports', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger))
        ->assertSuccessful();
});

test('free user is redirected from bills to premium page', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.bills.index', $ledger))
        ->assertRedirect(route('premium'));
});

test('premium user can access bills', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.bills.index', $ledger))
        ->assertSuccessful();
});

test('free user is redirected from budgets to premium page', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.budgets.index', $ledger))
        ->assertRedirect(route('premium'));
});

test('premium user can access budgets', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.budgets.index', $ledger))
        ->assertSuccessful();
});

test('premium page renders for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('premium'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('premium/index')
        );
});

test('free user with one ledger is redirected from ledger create page', function () {
    $user = User::factory()->create();
    Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.create'))
        ->assertRedirect(route('premium'));
});

test('free user with one ledger cannot create another', function () {
    $user = User::factory()->create();
    Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('ledgers.store'), [
            'name' => 'Second Workspace',
            'currency_code' => 'USD',
            'uses_seeded_categories' => true,
        ])
        ->assertForbidden();
});

test('free user with no ledger can create one', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('ledgers.store'), [
            'name' => 'First Workspace',
            'currency_code' => 'MYR',
            'uses_seeded_categories' => true,
        ])
        ->assertSessionHasNoErrors();
});

test('premium user can create multiple ledgers', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('ledgers.store'), [
            'name' => 'Second Workspace',
            'currency_code' => 'USD',
            'uses_seeded_categories' => true,
        ])
        ->assertSessionHasNoErrors();
});

test('free user with 7 accounts cannot create an 8th', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    Account::factory()->for($ledger)->for($accountType)->count(7)->create();

    $this->actingAs($user)
        ->post(route('ledgers.accounts.store', $ledger), [
            'account_type_id' => $accountType->id,
            'name' => 'Eighth Account',
            'initial_balance' => 0,
            'include_in_totals' => true,
        ])
        ->assertForbidden();
});

test('premium user can create more than 7 accounts', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    Account::factory()->for($ledger)->for($accountType)->count(7)->create();

    $this->actingAs($user)
        ->post(route('ledgers.accounts.store', $ledger), [
            'account_type_id' => $accountType->id,
            'name' => 'Eighth Account',
            'initial_balance' => 0,
            'include_in_totals' => true,
        ])
        ->assertSessionHasNoErrors();
});

test('free user cannot create transaction for account beyond first 7', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $accounts = Account::factory()->for($ledger)->for($accountType)->count(8)->create();
    $category = Category::factory()->for($ledger)->create(['transaction_type' => 'expense']);

    $eighthAccount = $accounts->sortBy('id')->values()->get(7);

    $this->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $eighthAccount->id,
            'category_id' => $category->id,
            'transaction_type' => 'expense',
            'amount' => 50.00,
            'transaction_date' => '2026-03-26',
        ])
        ->assertSessionHasErrors('account_id');
});

test('free user can create transaction for account within first 7', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $accounts = Account::factory()->for($ledger)->for($accountType)->count(8)->create();
    $category = Category::factory()->for($ledger)->create(['transaction_type' => 'expense']);

    $firstAccount = $accounts->sortBy('id')->values()->first();

    $this->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $firstAccount->id,
            'category_id' => $category->id,
            'transaction_type' => 'expense',
            'amount' => 50.00,
            'transaction_date' => '2026-03-26',
        ])
        ->assertSessionHasNoErrors();
});
