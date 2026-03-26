<?php

use App\Models\Ledger;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('unauthenticated users see the welcome page', function () {
    $this->withoutVite();

    $response = $this->get(route('home'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page->component('welcome'));
});

test('welcome page passes canRegister prop to frontend', function () {
    $this->withoutVite();

    $response = $this->get(route('home'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('welcome')
        ->has('canRegister')
    );
});

test('authenticated users with no ledgers are redirected to onboarding', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('home'));

    $response->assertRedirect(route('onboarding.show'));
});

test('authenticated users with ledgers are redirected to the lowest id ledger', function () {
    $user = User::factory()->create();
    $secondLedger = Ledger::factory()->for($user)->create();
    $firstLedger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('home'));

    $lowestIdLedger = $user->ledgers()->orderBy('id')->first();

    $response->assertRedirect(route('ledgers.dashboard', $lowestIdLedger));
});
