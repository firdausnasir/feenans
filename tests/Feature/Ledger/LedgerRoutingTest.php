<?php

use App\Models\Ledger;
use App\Models\User;

test('authenticated users can view the ledger index', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.index'));

    $response->assertSuccessful();
});

test('authenticated users can store a ledger', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.store'), [
            'name' => 'Personal',
            'currency_code' => 'MYR',
            'uses_seeded_categories' => true,
        ]);

    $response->assertRedirect();

    expect($user->fresh()->ledgers)->toHaveCount(1);
});

test('authenticated users can view a ledger dashboard', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger));

    $response->assertSuccessful();
});

test('web ledger route names are registered', function () {
    expect(app('router')->has('ledgers.transactions.index'))->toBeTrue()
        ->and(app('router')->has('ledgers.categories.store'))->toBeTrue()
        ->and(app('router')->has('ledgers.import.execute'))->toBeTrue()
        ->and(app('router')->has('ledgers.sample-data.destroy'))->toBeTrue();
});
