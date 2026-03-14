<?php

use App\Models\Category;
use App\Models\Ledger;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('deleted categories appear in the trash view', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create([
        'name' => 'Archived category',
    ]);

    $this->actingAs($user)
        ->delete(route('ledgers.categories.destroy', [$ledger, $category]))
        ->assertRedirect();

    $this->assertSoftDeleted('categories', ['id' => $category->id]);

    $this->actingAs($user)
        ->get(route('ledgers.categories.trash', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/categories/trash/index')
            ->has('categories', 1)
            ->where('categories.0.id', $category->id)
        );
});

test('users can restore a soft deleted category', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->trashed()->create();

    $this->actingAs($user)
        ->patch(route('ledgers.categories.restore', [$ledger, $category]))
        ->assertRedirect(route('ledgers.categories.trash', $ledger));

    $this->assertNotSoftDeleted('categories', ['id' => $category->id]);
});

test('users can permanently delete a soft deleted category', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->trashed()->create();

    $this->actingAs($user)
        ->delete(route('ledgers.categories.force-destroy', [$ledger, $category]))
        ->assertRedirect(route('ledgers.categories.trash', $ledger));

    expect(Category::withTrashed()->find($category->id))->toBeNull();
});
