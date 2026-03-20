<?php

use App\Models\Account;
use App\Models\Ledger;
use App\Models\User;

test('accounts index renders successfully', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    Account::factory()->for($ledger)->create(['name' => 'Visible']);
    Account::factory()->for($ledger)->hidden()->create(['name' => 'Hidden']);

    $this->actingAs($user)
        ->get(route('ledgers.accounts.index', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('ledgers/accounts/index')
        );
});

test('toggle visibility hides a visible account', function () {
    test()->markTestSkipped('Account visibility toggle was removed with the stale app-specific API layer.');
});

test('toggle visibility unhides a hidden account', function () {
    test()->markTestSkipped('Account visibility toggle was removed with the stale app-specific API layer.');
});

test('dashboard renders successfully', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    Account::factory()->for($ledger)->create(['name' => 'Visible']);
    Account::factory()->for($ledger)->hidden()->create(['name' => 'Hidden']);

    $this->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('ledgers/dashboard')
        );
});

test('visible scope filters out hidden accounts', function () {
    $ledger = Ledger::factory()->for(User::factory())->create();

    Account::factory()->for($ledger)->create(['is_hidden' => false]);
    Account::factory()->for($ledger)->hidden()->create();

    expect(Account::query()->visible()->count())->toBe(1);
});

test('hidden scope returns only hidden accounts', function () {
    $ledger = Ledger::factory()->for(User::factory())->create();

    Account::factory()->for($ledger)->create(['is_hidden' => false]);
    Account::factory()->for($ledger)->hidden()->create();

    expect(Account::query()->hidden()->count())->toBe(1);
});

test('bill create form renders successfully', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    Account::factory()->for($ledger)->create(['name' => 'Visible']);
    Account::factory()->for($ledger)->hidden()->create(['name' => 'Hidden']);

    $this->actingAs($user)
        ->get(route('ledgers.bills.create', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('ledgers/bills/create')
        );
});
