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

test('authenticated users with no ledgers see the welcome page', function () {
    $this->withoutVite();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('home'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page->component('welcome'));
});

test('authenticated users with ledgers see the welcome page', function () {
    $this->withoutVite();

    $user = User::factory()->create();
    Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('home'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page->component('welcome'));
});
