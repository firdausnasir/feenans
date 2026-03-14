<?php

use App\Models\Ledger;
use App\Models\Payee;
use App\Models\User;

test('users can create payees in a ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.payees.store', $ledger), [
            'name' => 'Local Cafe',
        ]);

    $response->assertRedirect();

    expect($ledger->payees()->where('name', 'Local Cafe')->exists())->toBeTrue();
});

test('payee index returns payees with transaction count', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    Payee::factory()->for($ledger)->create(['name' => 'Alpha']);
    Payee::factory()->for($ledger)->create(['name' => 'Beta']);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.payees.index', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/payees/index')
        ->has('payees', 2)
        ->has('ledger')
    );
});

test('payee update updates payee name', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $payee = Payee::factory()->for($ledger)->create(['name' => 'Old Payee']);

    $response = $this
        ->actingAs($user)
        ->put(route('ledgers.payees.update', [$ledger, $payee]), [
            'name' => 'New Payee',
        ]);

    $response->assertRedirect();

    expect($payee->fresh()->name)->toBe('New Payee');
});

test('payee destroy deletes payee', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $payee = Payee::factory()->for($ledger)->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('ledgers.payees.destroy', [$ledger, $payee]));

    $response->assertRedirect();

    expect(Payee::find($payee->id))->toBeNull();
});
