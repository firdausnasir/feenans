<?php

use App\Models\Account;
use App\Models\Ledger;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('accounts index hides hidden accounts by default', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $visible = Account::factory()->for($ledger)->create(['name' => 'Visible']);
    $hidden = Account::factory()->for($ledger)->hidden()->create(['name' => 'Hidden']);

    $this->actingAs($user)
        ->get(route('ledgers.accounts.index', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('accounts', 1)
            ->where('accounts.0.name', 'Visible')
            ->where('showHidden', false)
            ->etc()
        );
});

test('accounts index shows hidden accounts when show_hidden is true', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    Account::factory()->for($ledger)->create(['name' => 'Visible']);
    Account::factory()->for($ledger)->hidden()->create(['name' => 'Hidden']);

    $this->actingAs($user)
        ->get(route('ledgers.accounts.index', [$ledger, 'show_hidden' => 1]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('accounts', 2)
            ->where('showHidden', true)
            ->etc()
        );
});

test('toggle visibility hides a visible account', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $account = Account::factory()->for($ledger)->create(['is_hidden' => false]);

    $this->actingAs($user)
        ->patch(route('ledgers.accounts.toggle-visibility', [$ledger, $account]))
        ->assertRedirect();

    expect($account->fresh()->is_hidden)->toBeTrue();
});

test('toggle visibility unhides a hidden account', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $account = Account::factory()->for($ledger)->hidden()->create();

    $this->actingAs($user)
        ->patch(route('ledgers.accounts.toggle-visibility', [$ledger, $account]))
        ->assertRedirect();

    expect($account->fresh()->is_hidden)->toBeFalse();
});

test('dashboard excludes hidden accounts from flatAccounts', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    Account::factory()->for($ledger)->create(['name' => 'Visible']);
    Account::factory()->for($ledger)->hidden()->create(['name' => 'Hidden']);

    $this->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('flatAccounts', 1)
            ->where('flatAccounts.0.name', 'Visible')
            ->etc()
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

test('bill create form excludes hidden accounts', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    Account::factory()->for($ledger)->create(['name' => 'Visible']);
    Account::factory()->for($ledger)->hidden()->create(['name' => 'Hidden']);

    $this->actingAs($user)
        ->get(route('ledgers.bills.create', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('accounts', 1)
            ->where('accounts.0.name', 'Visible')
            ->etc()
        );
});
