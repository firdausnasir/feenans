<?php

use App\Models\Ledger;
use App\Models\User;

test('ledger creation requires a name', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.store'), [
            'name' => '',
            'currency_code' => 'MYR',
            'uses_seeded_categories' => true,
        ]);

    $response->assertSessionHasErrors(['name']);
});

test('ledger creation requires a three character currency code', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.store'), [
            'name' => 'Personal',
            'currency_code' => 'RINGGIT',
            'uses_seeded_categories' => true,
        ]);

    $response->assertSessionHasErrors(['currency_code']);
});

test('transaction creation requires a destination account for transfers', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), [
            'transaction_type' => 'transfer',
            'account_id' => 999,
            'to_account_id' => null,
            'amount' => 100,
            'transaction_date' => '2026-03-13',
        ]);

    $response->assertSessionHasErrors(['to_account_id']);
});

test('ledger destroy deletes the ledger and redirects to index', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('ledgers.destroy', $ledger));

    $response->assertRedirect(route('ledgers.index'));

    expect(Ledger::find($ledger->id))->toBeNull();
});

test('ledger destroy is forbidden for non-owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();

    $response = $this
        ->actingAs($other)
        ->delete(route('ledgers.destroy', $ledger));

    $response->assertForbidden();

    expect(Ledger::find($ledger->id))->not->toBeNull();
});

test('settings index returns 200 with ledger data', function () {
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

test('settings update updates ledger name and cycle_start_day', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create([
        'name' => 'Old Name',
        'cycle_start_day' => 1,
    ]);

    $response = $this
        ->actingAs($user)
        ->putJson(route('api.v1.ledgers.settings.update', $ledger), [
            'name' => 'Updated Name',
            'cycle_start_day' => 15,
        ]);

    $response->assertOk();

    expect($ledger->fresh()->name)->toBe('Updated Name')
        ->and($ledger->fresh()->cycle_start_day)->toBe(15);
});
