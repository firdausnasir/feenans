<?php

use App\Models\Ledger;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users without a ledger are redirected to ledger creation', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertRedirect(route('ledgers.create'));
});

test('authenticated users with ledgers are redirected to their current ledger dashboard', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertRedirect(route('ledgers.dashboard', $ledger));
});

test('active ledger context is shared with ledger pages', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create([
        'name' => 'Personal',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('currentLedger.id', $ledger->id)
        ->where('currentLedger.name', 'Personal')
        ->has('availableLedgers', 1)
        ->etc()
    );
});
