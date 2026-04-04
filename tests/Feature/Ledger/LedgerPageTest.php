<?php

use App\Models\Ledger;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('ledger index page renders shell without data props', function () {
    $user = User::factory()->create();
    Ledger::factory()->for($user)->create([
        'name' => 'Personal',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/index')
        ->missing('ledgers')
    );
});

test('ledger create page returns initial form props', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.create'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/create')
        ->where('defaults.currency_code', 'MYR')
        ->where('defaults.uses_seeded_categories', true)
        ->etc()
    );
});
