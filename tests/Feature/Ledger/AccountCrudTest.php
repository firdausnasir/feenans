<?php

use App\Actions\Accounts\UseCases\AdjustAccountBalanceAction;
use App\Actions\Accounts\UseCases\DeleteAccountAction;
use App\Actions\Accounts\UseCases\ReorderAccountsAction;
use App\Actions\Accounts\UseCases\StoreAccountAction;
use App\Actions\Accounts\UseCases\UpdateAccountAction;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Ledger;
use App\Models\User;

beforeEach(function () {
    config()->set('app.paywall_enabled', true);
});

test('account index renders successfully', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.accounts.index', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/accounts/index')
    );
});

test('account store routes through StoreAccountAction', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();

    $called = false;
    $real = app()->make(StoreAccountAction::class);
    app()->bind(StoreAccountAction::class, function () use ($real, &$called) {
        $called = true;

        return $real;
    });

    $this->actingAs($user)
        ->from(route('ledgers.accounts.index', $ledger))
        ->post(route('ledgers.accounts.store', $ledger), [
            'account_type_id' => $accountType->id,
            'name' => 'Test Account',
            'initial_balance' => 0,
            'include_in_totals' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Account created.');

    expect($called)->toBeTrue();
});

test('account update routes through UpdateAccountAction', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create(['name' => 'Old']);

    $called = false;
    $real = app()->make(UpdateAccountAction::class);
    app()->bind(UpdateAccountAction::class, function () use ($real, &$called) {
        $called = true;

        return $real;
    });

    $this->actingAs($user)
        ->from(route('ledgers.accounts.index', $ledger))
        ->put(route('ledgers.accounts.update', [$ledger, $account]), [
            'name' => 'New',
            'account_type_id' => $accountType->id,
            'initial_balance' => 0,
            'include_in_totals' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Account updated.');

    expect($called)->toBeTrue();
});

test('account destroy routes through DeleteAccountAction', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $called = false;
    $real = app()->make(DeleteAccountAction::class);
    app()->bind(DeleteAccountAction::class, function () use ($real, &$called) {
        $called = true;

        return $real;
    });

    $this->actingAs($user)
        ->from(route('ledgers.accounts.index', $ledger))
        ->delete(route('ledgers.accounts.destroy', [$ledger, $account]))
        ->assertRedirect()
        ->assertSessionHas('success', 'Account deleted.');

    expect($called)->toBeTrue();
});

test('account reorder routes through ReorderAccountsAction', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create(['position' => 1]);

    $called = false;
    $real = app()->make(ReorderAccountsAction::class);
    app()->bind(ReorderAccountsAction::class, function () use ($real, &$called) {
        $called = true;

        return $real;
    });

    $this->actingAs($user)
        ->from(route('ledgers.accounts.index', $ledger))
        ->post(route('ledgers.accounts.reorder', $ledger), [
            'items' => [['id' => $account->id, 'position' => 1]],
        ])
        ->assertRedirect();

    expect($called)->toBeTrue();
});

test('account adjust balance routes through AdjustAccountBalanceAction', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $called = false;
    $real = app()->make(AdjustAccountBalanceAction::class);
    app()->bind(AdjustAccountBalanceAction::class, function () use ($real, &$called) {
        $called = true;

        return $real;
    });

    $this->actingAs($user)
        ->from(route('ledgers.accounts.index', $ledger))
        ->post(route('ledgers.accounts.adjust-balance', [$ledger, $account]), [
            'amount' => 100,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Balance adjusted.');

    expect($called)->toBeTrue();
});

test('free plan user is blocked from creating 8th account', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();

    Account::factory()->for($ledger)->for($accountType)->count(7)->create();

    $this->actingAs($user)
        ->from(route('ledgers.accounts.index', $ledger))
        ->post(route('ledgers.accounts.store', $ledger), [
            'account_type_id' => $accountType->id,
            'name' => 'Eighth Account',
            'initial_balance' => 0,
            'include_in_totals' => true,
        ])
        ->assertForbidden();
});

test('premium user can create 8th account', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();

    Account::factory()->for($ledger)->for($accountType)->count(7)->create();

    $this->actingAs($user)
        ->from(route('ledgers.accounts.index', $ledger))
        ->post(route('ledgers.accounts.store', $ledger), [
            'account_type_id' => $accountType->id,
            'name' => 'Eighth Account',
            'initial_balance' => 0,
            'include_in_totals' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});
