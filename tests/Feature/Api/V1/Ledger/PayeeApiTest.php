<?php

use App\Models\Ledger;
use App\Models\Payee;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('token authenticated client can list payees for a ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    Payee::factory()->for($ledger)->create(['name' => 'Travel']);
    Payee::factory()->for($ledger)->create(['name' => 'Bills']);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson(route('api.v1.ledgers.payees.index', $ledger));

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Bills')
        ->assertJsonPath('data.1.name', 'Travel')
        ->assertJsonPath('data.0.transactions_count', 0);
});

test('payee api create returns validation errors as json', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson(route('api.v1.ledgers.payees.store', $ledger), [
        'name' => '',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('payee api create returns created payee contract', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson(route('api.v1.ledgers.payees.store', $ledger), [
        'name' => 'Travel',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.ledger_id', $ledger->id)
        ->assertJsonPath('data.name', 'Travel')
        ->assertJsonPath('data.transactions_count', 0)
        ->assertJsonPath('data.created_at', fn (mixed $createdAt): bool => is_string($createdAt) && $createdAt !== '')
        ->assertJsonPath('data.updated_at', fn (mixed $updatedAt): bool => is_string($updatedAt) && $updatedAt !== '');

    expect($ledger->payees()->where('name', 'Travel')->exists())->toBeTrue();
});

test('payee api update returns updated payee json', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $payee = Payee::factory()->for($ledger)->create([
        'name' => 'Old Name',
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->patchJson(route('api.v1.ledgers.payees.update', [$ledger, $payee]), [
        'name' => 'New Name',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $payee->id)
        ->assertJsonPath('data.ledger_id', $ledger->id)
        ->assertJsonPath('data.name', 'New Name');

    expect($payee->fresh()->name)->toBe('New Name');
});

test('payee api update returns validation errors as json and preserves stored data', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $payee = Payee::factory()->for($ledger)->create([
        'name' => 'Existing Name',
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->patchJson(route('api.v1.ledgers.payees.update', [$ledger, $payee]), [
        'name' => '',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    expect($payee->fresh()->name)->toBe('Existing Name');
});

test('payee api delete returns deleted payee json', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $payee = Payee::factory()->for($ledger)->create([
        'name' => 'Delete Me',
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->deleteJson(route('api.v1.ledgers.payees.destroy', [$ledger, $payee]));

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $payee->id)
        ->assertJsonPath('data.name', 'Delete Me');

    expect(Payee::query()->whereKey($payee->id)->exists())->toBeFalse();
});

test('payee api returns json forbidden when ledger policy denies access', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();
    $payee = Payee::factory()->for($ledger)->create();

    Sanctum::actingAs($outsider, ['*']);

    $this->getJson(route('api.v1.ledgers.payees.index', $ledger))
        ->assertForbidden();

    $this->postJson(route('api.v1.ledgers.payees.store', $ledger), [
        'name' => 'Blocked',
    ])->assertForbidden();

    $this->patchJson(route('api.v1.ledgers.payees.update', [$ledger, $payee]), [
        'name' => 'Blocked',
    ])->assertForbidden();

    $this->deleteJson(route('api.v1.ledgers.payees.destroy', [$ledger, $payee]))
        ->assertForbidden();
});

test('payee api rejects guest requests', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $payee = Payee::factory()->for($ledger)->create();

    $this->getJson(route('api.v1.ledgers.payees.index', $ledger))
        ->assertUnauthorized();

    $this->postJson(route('api.v1.ledgers.payees.store', $ledger), [
        'name' => 'Guest',
    ])->assertUnauthorized();

    $this->patchJson(route('api.v1.ledgers.payees.update', [$ledger, $payee]), [
        'name' => 'Guest',
    ])->assertUnauthorized();

    $this->deleteJson(route('api.v1.ledgers.payees.destroy', [$ledger, $payee]))
        ->assertUnauthorized();
});
