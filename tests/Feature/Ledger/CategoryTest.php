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
        ->from(route('ledgers.categories.index', $ledger))
        ->post(route('ledgers.categories.store', $ledger), [
            'name' => 'Subscriptions',
            'transaction_type' => 'expense',
            'color' => '#0f172a',
            'icon' => 'tv',
        ]);

    $response->assertRedirect(route('ledgers.categories.index', $ledger))
        ->assertSessionHasNoErrors();

    expect($ledger->categories()->where('name', 'Subscriptions')->exists())->toBeTrue();
});

test('category index renders the inertia shell for api-driven category data', function () {
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
        ->where('currentLedger.id', $ledger->id)
        ->missing('categories')
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->has('categories', 1, fn (Assert $categoryPage) => $categoryPage
                ->where('name', 'Food')
                ->has('children', 1)
                ->where('children.0.name', 'Restaurants')
                ->etc()
            )
        )
    );
});

test('category can be created through web routes', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.categories.index', $ledger))
        ->post(route('ledgers.categories.store', $ledger), [
            'name' => 'Subscriptions',
            'transaction_type' => 'expense',
            'color' => '#0f172a',
            'icon' => 'tv',
        ]);

    $response->assertRedirect(route('ledgers.categories.index', $ledger))
        ->assertSessionHasNoErrors();

    expect($ledger->categories()->where('name', 'Subscriptions')->exists())->toBeTrue();
});

test('category index supports partial reloads for categories', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    Category::factory()->for($ledger)->create(['name' => 'Subscriptions']);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.categories.index', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->reloadOnly('categories', fn (Assert $reload) => $reload
            ->has('categories', 1, fn (Assert $categoryPage) => $categoryPage
                ->where('name', 'Subscriptions')
                ->etc()
            )
            ->missing('currentLedger')
        )
    );
});

test('category update updates the category', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create(['name' => 'Old Name']);

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.categories.index', $ledger))
        ->patch(route('ledgers.categories.update', [$ledger, $category]), [
            'name' => 'New Name',
            'transaction_type' => 'expense',
        ]);

    $response->assertRedirect(route('ledgers.categories.index', $ledger))
        ->assertSessionHasNoErrors();

    expect($category->fresh()->name)->toBe('New Name');
});

test('category update rejects a parent from another ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create(['name' => 'Old Name']);
    $foreignLedger = Ledger::factory()->create();
    $foreignParent = Category::factory()->for($foreignLedger)->create();

    $response = $this->actingAs($user)
        ->from(route('ledgers.categories.index', $ledger))
        ->patch(route('ledgers.categories.update', [$ledger, $category]), [
            'name' => 'New Name',
            'transaction_type' => 'expense',
            'parent_id' => $foreignParent->id,
        ]);

    $response->assertRedirect(route('ledgers.categories.index', $ledger))
        ->assertSessionHasErrors('parent_id');
});

test('category destroy deletes category without transactions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create();

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.categories.index', $ledger))
        ->delete(route('ledgers.categories.destroy', [$ledger, $category]));

    $response->assertRedirect(route('ledgers.categories.index', $ledger));

    expect(Category::find($category->id))->toBeNull();
});

test('category reorder updates positions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $cat1 = Category::factory()->for($ledger)->create(['position' => 1]);
    $cat2 = Category::factory()->for($ledger)->create(['position' => 2]);

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.categories.index', $ledger))
        ->post(route('ledgers.categories.reorder', $ledger), [
            'items' => [
                ['id' => $cat1->id, 'position' => 2],
                ['id' => $cat2->id, 'position' => 1],
            ],
        ]);

    $response->assertRedirect(route('ledgers.categories.index', $ledger))
        ->assertSessionHasNoErrors();

    expect($cat1->fresh()->position)->toBe(2)
        ->and($cat2->fresh()->position)->toBe(1);
});
