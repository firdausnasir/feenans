<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Ledger;
use App\Models\User;

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

test('account create page renders', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.accounts.create', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/accounts/create')
    );
});

test('account edit page renders', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.accounts.edit', [$ledger, $account]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/accounts/edit')
        ->where('accountId', $account->id)
    );
});

test('account show renders for credit account', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $creditType = AccountType::factory()->for($ledger)->credit()->create();
    $account = Account::factory()->for($ledger)->for($creditType)->create([
        'statement_day' => 15,
        'initial_balance' => 0.00,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.accounts.show', [$ledger, $account]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/accounts/show')
        ->where('accountId', $account->id)
    );
});
