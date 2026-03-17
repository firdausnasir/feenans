<?php

use App\Models\Account;
use App\Models\Ledger;
use App\Models\User;

test('reorder endpoint updates account positions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $accountA = Account::factory()->for($ledger)->create(['name' => 'A', 'position' => 1]);
    $accountB = Account::factory()->for($ledger)->create(['name' => 'B', 'position' => 2]);
    $accountC = Account::factory()->for($ledger)->create(['name' => 'C', 'position' => 3]);

    $this->actingAs($user)
        ->post(route('ledgers.accounts.reorder', $ledger), [
            'items' => [
                ['id' => $accountC->id, 'position' => 1],
                ['id' => $accountA->id, 'position' => 2],
                ['id' => $accountB->id, 'position' => 3],
            ],
        ])
        ->assertRedirect();

    expect($accountC->fresh()->position)->toBe(1)
        ->and($accountA->fresh()->position)->toBe(2)
        ->and($accountB->fresh()->position)->toBe(3);
});

test('accounts index renders successfully', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    Account::factory()->for($ledger)->create(['name' => 'Zeta', 'position' => 1]);
    Account::factory()->for($ledger)->create(['name' => 'Alpha', 'position' => 2]);

    $this->actingAs($user)
        ->get(route('ledgers.accounts.index', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('ledgers/accounts/index')
        );
});

test('reorder only updates accounts belonging to the ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $otherLedger = Ledger::factory()->for($user)->create();

    $account = Account::factory()->for($ledger)->create(['position' => 1]);
    $otherAccount = Account::factory()->for($otherLedger)->create(['position' => 1]);

    $this->actingAs($user)
        ->post(route('ledgers.accounts.reorder', $ledger), [
            'items' => [
                ['id' => $account->id, 'position' => 5],
                ['id' => $otherAccount->id, 'position' => 10],
            ],
        ])
        ->assertRedirect();

    // Own ledger's account should be updated
    expect($account->fresh()->position)->toBe(5);

    // Other ledger's account should NOT be updated
    expect($otherAccount->fresh()->position)->toBe(1);
});

test('reorder validates required fields', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('ledgers.accounts.reorder', $ledger), [])
        ->assertSessionHasErrors('items');
});

test('reorder validates items structure', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('ledgers.accounts.reorder', $ledger), [
            'items' => [
                ['id' => 'not-a-number', 'position' => 'also-not'],
            ],
        ])
        ->assertSessionHasErrors(['items.0.id', 'items.0.position']);
});

test('reorder requires authentication', function () {
    $ledger = Ledger::factory()->for(User::factory())->create();

    $this->post(route('ledgers.accounts.reorder', $ledger))
        ->assertRedirect(route('login'));
});
