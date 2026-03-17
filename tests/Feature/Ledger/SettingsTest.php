<?php

use App\Models\Ledger;
use App\Models\User;

test('settings page renders for ledger owner', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.settings.index', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/settings/index')
    );
});

test('settings page is forbidden for non-owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();

    $this->actingAs($other)
        ->get(route('ledgers.settings.index', $ledger))
        ->assertForbidden();
});

test('settings update changes ledger name and cycle day', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create([
        'name' => 'Old Name',
        'cycle_start_day' => 1,
    ]);

    $response = $this
        ->actingAs($user)
        ->putJson(route('api.v1.ledgers.settings.update', $ledger), [
            'name' => 'New Name',
            'currency_code' => 'USD',
            'cycle_start_day' => 15,
        ]);

    $response->assertOk();

    $ledger->refresh();
    expect($ledger->name)->toBe('New Name')
        ->and($ledger->cycle_start_day)->toBe(15);
});

test('settings update is forbidden for non-owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();

    $this->actingAs($other)
        ->put(route('api.v1.ledgers.settings.update', $ledger), [
            'name' => 'Hacked',
            'currency_code' => 'USD',
            'cycle_start_day' => 1,
        ])
        ->assertForbidden();
});

test('unauthenticated users cannot access settings', function () {
    $ledger = Ledger::factory()->create();

    $this->get(route('ledgers.settings.index', $ledger))
        ->assertRedirect(route('login'));
});
