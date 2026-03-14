<?php

use App\Models\Category;
use App\Models\Ledger;

test('category can have a parent category', function () {
    $ledger = Ledger::factory()->create();

    $parent = Category::factory()->for($ledger)->create();
    $child = Category::factory()->for($ledger)->create(['parent_id' => $parent->id]);

    expect($child->parent->id)->toBe($parent->id);
});

test('category scopes parents returns only root categories', function () {
    $ledger = Ledger::factory()->create();

    $parent = Category::factory()->for($ledger)->create();
    Category::factory()->for($ledger)->create(['parent_id' => $parent->id]);

    $parents = Category::query()->parents()->get();

    expect($parents)->toHaveCount(1);
    expect($parents->first()->id)->toBe($parent->id);
});

test('children relationship returns child categories', function () {
    $ledger = Ledger::factory()->create();

    $parent = Category::factory()->for($ledger)->create();
    $child1 = Category::factory()->for($ledger)->create(['parent_id' => $parent->id]);
    $child2 = Category::factory()->for($ledger)->create(['parent_id' => $parent->id]);

    $children = $parent->children;

    expect($children)->toHaveCount(2);
    expect($children->pluck('id')->sort()->values()->toArray())
        ->toBe(collect([$child1->id, $child2->id])->sort()->values()->toArray());
});

test('deleting parent category cascades to children', function () {
    $ledger = Ledger::factory()->create();

    $parent = Category::factory()->for($ledger)->create();
    $child = Category::factory()->for($ledger)->create(['parent_id' => $parent->id]);

    $parent->delete();

    expect(Category::find($child->id))->toBeNull();
});
