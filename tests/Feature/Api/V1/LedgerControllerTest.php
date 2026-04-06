<?php

use App\Models\Ledger;
use App\Models\User;

test('api ledger index returns user ledgers', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create([
        'name' => 'Personal',
        'currency_code' => 'MYR',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/ledgers');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment([
            'id' => $ledger->id,
            'name' => 'Personal',
            'currency_code' => 'MYR',
        ]);
});

test('api ledger index only returns own ledgers', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Ledger::factory()->for($user)->create(['name' => 'Mine']);
    Ledger::factory()->for($other)->create(['name' => 'Theirs']);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/ledgers');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Mine');
});

test('api ledger show returns ledger details', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create([
        'name' => 'Personal',
        'currency_code' => 'MYR',
        'cycle_start_day' => 15,
        'uses_seeded_categories' => true,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/ledgers/{$ledger->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $ledger->id)
        ->assertJsonPath('data.name', 'Personal')
        ->assertJsonPath('data.currency_code', 'MYR')
        ->assertJsonPath('data.cycle_start_day', 15)
        ->assertJsonPath('data.uses_seeded_categories', true);
});

test('api ledger show is forbidden for non-owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();

    $this->actingAs($other, 'sanctum')
        ->getJson("/api/v1/ledgers/{$ledger->id}")
        ->assertForbidden();
});

test('api has-sample-data returns boolean', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/ledgers/{$ledger->id}/has-sample-data");

    $response->assertOk()
        ->assertJsonPath('data', false);
});

test('api has-sample-data is forbidden for non-owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();

    $this->actingAs($other, 'sanctum')
        ->getJson("/api/v1/ledgers/{$ledger->id}/has-sample-data")
        ->assertForbidden();
});
