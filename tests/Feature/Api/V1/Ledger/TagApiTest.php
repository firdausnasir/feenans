<?php

use App\Models\Ledger;
use App\Models\Tag;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('token authenticated client can list tags for a ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    Tag::factory()->for($ledger)->create(['name' => 'Travel', 'color' => '#22c55e']);
    Tag::factory()->for($ledger)->create(['name' => 'Bills', 'color' => '#ef4444']);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson(route('api.v1.ledgers.tags.index', $ledger));

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Bills')
        ->assertJsonPath('data.1.name', 'Travel')
        ->assertJsonPath('data.0.transactions_count', 0);
});

test('tag api create returns validation errors as json', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson(route('api.v1.ledgers.tags.store', $ledger), [
        'name' => '',
        'color' => 'invalid-color',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'color'])
        ->assertJsonPath('errors.name.0', 'Please enter a tag name.')
        ->assertJsonPath('errors.color.0', 'Please enter a valid hex color like #FF0000.');
});

test('tag api create returns created tag contract', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson(route('api.v1.ledgers.tags.store', $ledger), [
        'name' => 'Travel',
        'color' => '#22c55e',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.ledger_id', $ledger->id)
        ->assertJsonPath('data.name', 'Travel')
        ->assertJsonPath('data.color', '#22c55e')
        ->assertJsonPath('data.transactions_count', 0)
        ->assertJsonPath('data.created_at', fn (mixed $createdAt): bool => is_string($createdAt) && $createdAt !== '')
        ->assertJsonPath('data.updated_at', fn (mixed $updatedAt): bool => is_string($updatedAt) && $updatedAt !== '');
});

test('tag api update returns updated tag json', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $tag = Tag::factory()->for($ledger)->create([
        'name' => 'Old Name',
        'color' => '#6366f1',
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->patchJson(route('api.v1.ledgers.tags.update', [$ledger, $tag]), [
        'name' => 'New Name',
        'color' => '#22c55e',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $tag->id)
        ->assertJsonPath('data.ledger_id', $ledger->id)
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.color', '#22c55e');
});

test('tag api update rejects duplicate names within the same ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    Tag::factory()->for($ledger)->create(['name' => 'Existing']);
    $tag = Tag::factory()->for($ledger)->create(['name' => 'Editable']);

    Sanctum::actingAs($user, ['*']);

    $response = $this->patchJson(route('api.v1.ledgers.tags.update', [$ledger, $tag]), [
        'name' => 'Existing',
        'color' => '#22c55e',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    expect($tag->fresh()->name)->toBe('Editable');
});

test('tag api delete returns deleted tag json', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $tag = Tag::factory()->for($ledger)->create([
        'name' => 'Delete Me',
        'color' => '#ef4444',
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->deleteJson(route('api.v1.ledgers.tags.destroy', [$ledger, $tag]));

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $tag->id)
        ->assertJsonPath('data.name', 'Delete Me');

    expect(Tag::query()->whereKey($tag->id)->exists())->toBeFalse();
});

test('tag api returns json forbidden when ledger policy denies access', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();
    $tag = Tag::factory()->for($ledger)->create();

    Sanctum::actingAs($outsider, ['*']);

    $this->getJson(route('api.v1.ledgers.tags.index', $ledger))
        ->assertForbidden();

    $this->postJson(route('api.v1.ledgers.tags.store', $ledger), [
        'name' => 'Blocked',
        'color' => '#22c55e',
    ])->assertForbidden();

    $this->patchJson(route('api.v1.ledgers.tags.update', [$ledger, $tag]), [
        'name' => 'Blocked',
        'color' => '#3b82f6',
    ])->assertForbidden();

    $this->deleteJson(route('api.v1.ledgers.tags.destroy', [$ledger, $tag]))
        ->assertForbidden();
});

test('tag api rejects guest requests', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $tag = Tag::factory()->for($ledger)->create();

    $this->getJson(route('api.v1.ledgers.tags.index', $ledger))
        ->assertUnauthorized();

    $this->postJson(route('api.v1.ledgers.tags.store', $ledger), [
        'name' => 'Guest',
        'color' => '#22c55e',
    ])->assertUnauthorized();

    $this->patchJson(route('api.v1.ledgers.tags.update', [$ledger, $tag]), [
        'name' => 'Guest',
        'color' => '#22c55e',
    ])->assertUnauthorized();

    $this->deleteJson(route('api.v1.ledgers.tags.destroy', [$ledger, $tag]))
        ->assertUnauthorized();
});
