<?php

use App\Models\Category;
use App\Models\Ledger;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('users can create categories in a ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->postJson(route('api.v1.ledgers.categories.store', $ledger), [
            'name' => 'Subscriptions',
            'transaction_type' => 'expense',
            'color' => '#0f172a',
            'icon' => 'tv',
        ]);

    $response->assertStatus(201);

    expect($ledger->categories()->where('name', 'Subscriptions')->exists())->toBeTrue();
});

test('category index returns categories with children', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $parent = Category::factory()->for($ledger)->create([
        'name' => 'Food',
        'parent_id' => null,
        'position' => 1,
    ]);

    $child = Category::factory()->for($ledger)->create([
        'name' => 'Restaurants',
        'parent_id' => $parent->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.categories.index', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/categories/index')
        ->missing('categories')
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->has('categories', 1)
            ->where('categories.0.name', 'Food')
            ->has('categories.0.children', 1)
            ->where('categories.0.children.0.name', 'Restaurants')
        )
    );
});

test('category update updates the category', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create(['name' => 'Old Name']);

    $response = $this
        ->actingAs($user)
        ->putJson(route('api.v1.ledgers.categories.update', [$ledger, $category]), [
            'name' => 'New Name',
            'transaction_type' => 'expense',
        ]);

    $response->assertOk();

    expect($category->fresh()->name)->toBe('New Name');
});

test('category update rejects a parent from another ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create(['name' => 'Old Name']);
    $foreignLedger = Ledger::factory()->create();
    $foreignParent = Category::factory()->for($foreignLedger)->create();

    $response = $this->actingAs($user)
        ->putJson(route('api.v1.ledgers.categories.update', [$ledger, $category]), [
            'name' => 'New Name',
            'transaction_type' => 'expense',
            'parent_id' => $foreignParent->id,
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors('parent_id');
});

test('category destroy deletes category without transactions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create();

    $response = $this
        ->actingAs($user)
        ->deleteJson(route('api.v1.ledgers.categories.destroy', [$ledger, $category]));

    $response->assertNoContent();

    expect(Category::find($category->id))->toBeNull();
});

test('category reorder updates positions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $cat1 = Category::factory()->for($ledger)->create(['position' => 1]);
    $cat2 = Category::factory()->for($ledger)->create(['position' => 2]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('api.v1.ledgers.categories.reorder', $ledger), [
            'items' => [
                ['id' => $cat1->id, 'position' => 2],
                ['id' => $cat2->id, 'position' => 1],
            ],
        ]);

    $response->assertOk();

    expect($cat1->fresh()->position)->toBe(2)
        ->and($cat2->fresh()->position)->toBe(1);
});

test('ledger category web routes are available for inertia actions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create();

    expect(parse_url(route('ledgers.categories.store', $ledger), PHP_URL_PATH))->toBe("/ledgers/{$ledger->id}/categories")
        ->and(parse_url(route('ledgers.categories.update', [$ledger, $category]), PHP_URL_PATH))->toBe("/ledgers/{$ledger->id}/categories/{$category->id}")
        ->and(parse_url(route('ledgers.categories.destroy', [$ledger, $category]), PHP_URL_PATH))->toBe("/ledgers/{$ledger->id}/categories/{$category->id}")
        ->and(parse_url(route('ledgers.categories.reorder', $ledger), PHP_URL_PATH))->toBe("/ledgers/{$ledger->id}/categories/reorder");
});
