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
    );

    $response->assertViewMissing('page.deferredProps');
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

test('category web create uses correct redirect and flash under shared actions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $filteredUrl = route('ledgers.categories.index', ['ledger' => $ledger, 'tab' => 'income']);

    $response = $this->actingAs($user)
        ->from($filteredUrl)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->post(route('ledgers.categories.store', $ledger), [
            'name' => 'shared-create',
            'transaction_type' => 'expense',
            'color' => '#0f172a',
            'icon' => 'tv',
        ]);

    $response->assertRedirect($filteredUrl)
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Category created.');
});

test('category index keeps only shell props for api-backed reads', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $parent = Category::factory()->for($ledger)->create([
        'name' => 'Housing',
        'transaction_type' => 'expense',
        'color' => '#334155',
        'icon' => 'home',
        'position' => 1,
    ]);

    Category::factory()->for($ledger)->create([
        'name' => 'Rent',
        'parent_id' => $parent->id,
        'transaction_type' => 'expense',
        'color' => '#64748b',
        'icon' => 'building',
        'position' => 1,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.categories.index', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/categories/index')
        ->where('currentLedger.id', $ledger->id)
        ->missing('categories')
    );

    $response->assertViewMissing('page.deferredProps');
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

test('category web update uses correct redirect and flash under shared actions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create(['name' => 'before-update']);
    $filteredUrl = route('ledgers.categories.index', ['ledger' => $ledger, 'tab' => 'expense']);

    $response = $this->actingAs($user)
        ->from($filteredUrl)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->patch(route('ledgers.categories.update', [$ledger, $category]), [
            'name' => 'after-update',
            'transaction_type' => 'expense',
            'color' => '#3b82f6',
            'icon' => 'wallet',
        ]);

    $response->assertRedirect($filteredUrl)
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Category updated.');
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

test('category web delete uses correct redirect and flash under shared actions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create(['name' => 'delete-shared']);
    $filteredUrl = route('ledgers.categories.index', ['ledger' => $ledger, 'tab' => 'expense']);

    $response = $this->actingAs($user)
        ->from($filteredUrl)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->delete(route('ledgers.categories.destroy', [$ledger, $category]), [
            'reassign_category_id' => null,
        ]);

    $response->assertRedirect($filteredUrl)
        ->assertSessionHas('success', 'Category deleted.');
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

test('category web reorder uses correct redirect behavior under shared actions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $cat1 = Category::factory()->for($ledger)->create(['position' => 1]);
    $cat2 = Category::factory()->for($ledger)->create(['position' => 2]);
    $filteredUrl = route('ledgers.categories.index', ['ledger' => $ledger, 'tab' => 'expense']);

    $response = $this->actingAs($user)
        ->from($filteredUrl)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->post(route('ledgers.categories.reorder', $ledger), [
            'items' => [
                ['id' => $cat1->id, 'position' => 2],
                ['id' => $cat2->id, 'position' => 1],
            ],
        ]);

    $response->assertRedirect($filteredUrl)
        ->assertSessionHasNoErrors();
});

test('category web routes continue to enforce ledger authorization through shared actions', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();
    $category = Category::factory()->for($ledger)->create();

    $this->actingAs($outsider)
        ->get(route('ledgers.categories.index', $ledger))
        ->assertForbidden();

    $this->actingAs($outsider)
        ->post(route('ledgers.categories.store', $ledger), [
            'name' => 'forbidden-create',
            'transaction_type' => 'expense',
            'color' => '#22c55e',
            'icon' => 'lock',
        ])
        ->assertForbidden();

    $this->actingAs($outsider)
        ->patch(route('ledgers.categories.update', [$ledger, $category]), [
            'name' => 'forbidden-update',
            'transaction_type' => 'expense',
            'color' => '#3b82f6',
            'icon' => 'lock',
        ])
        ->assertForbidden();

    $this->actingAs($outsider)
        ->delete(route('ledgers.categories.destroy', [$ledger, $category]), [
            'reassign_category_id' => null,
        ])
        ->assertForbidden();

    $this->actingAs($outsider)
        ->post(route('ledgers.categories.reorder', $ledger), [
            'items' => [
                ['id' => $category->id, 'position' => 1],
            ],
        ])
        ->assertForbidden();
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
