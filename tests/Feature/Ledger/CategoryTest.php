<?php

use App\Models\Category;
use App\Models\Ledger;
use App\Models\User;

test('users can create categories in a ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.categories.store', $ledger), [
            'name' => 'Subscriptions',
            'transaction_type' => 'expense',
            'color' => '#0f172a',
            'icon' => 'tv',
        ]);

    $response->assertRedirect();

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
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/categories/index')
    );
});

test('category update updates the category', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create(['name' => 'Old Name']);

    $response = $this
        ->actingAs($user)
        ->put(route('ledgers.categories.update', [$ledger, $category]), [
            'name' => 'New Name',
            'transaction_type' => 'expense',
        ]);

    $response->assertRedirect();

    expect($category->fresh()->name)->toBe('New Name');
});

test('category update rejects a parent from another ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create(['name' => 'Old Name']);
    $foreignLedger = Ledger::factory()->create();
    $foreignParent = Category::factory()->for($foreignLedger)->create();

    $response = $this->actingAs($user)
        ->put(route('ledgers.categories.update', [$ledger, $category]), [
            'name' => 'New Name',
            'transaction_type' => 'expense',
            'parent_id' => $foreignParent->id,
        ]);

    $response->assertSessionHasErrors('parent_id');
});

test('category destroy deletes category without transactions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('ledgers.categories.destroy', [$ledger, $category]));

    $response->assertRedirect();

    expect(Category::find($category->id))->toBeNull();
});

test('category reorder updates positions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $cat1 = Category::factory()->for($ledger)->create(['position' => 1]);
    $cat2 = Category::factory()->for($ledger)->create(['position' => 2]);

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.categories.reorder', $ledger), [
            'items' => [
                ['id' => $cat1->id, 'position' => 2],
                ['id' => $cat2->id, 'position' => 1],
            ],
        ]);

    $response->assertRedirect();

    expect($cat1->fresh()->position)->toBe(2)
        ->and($cat2->fresh()->position)->toBe(1);
});
