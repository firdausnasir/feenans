<?php

use App\Models\Budget;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Tag;
use App\Models\User;

test('deleting a budget soft deletes it', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create();

    $budget = Budget::query()->create([
        'ledger_id' => $ledger->id,
        'category_id' => $category->id,
        'amount' => 500,
        'period' => 'monthly',
        'start_date' => now()->toDateString(),
        'end_date' => null,
        'is_active' => true,
        'rollover' => false,
    ]);

    $this->actingAs($user)
        ->delete(route('ledgers.budgets.destroy', [$ledger, $budget]))
        ->assertRedirect();

    $this->assertSoftDeleted('budgets', ['id' => $budget->id]);
});

test('soft deleted budget is excluded from index listing', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create();

    $budget = Budget::query()->create([
        'ledger_id' => $ledger->id,
        'category_id' => $category->id,
        'amount' => 500,
        'period' => 'monthly',
        'start_date' => now()->toDateString(),
        'end_date' => null,
        'is_active' => true,
        'rollover' => false,
    ]);

    $budget->delete();

    $this->actingAs($user)
        ->get(route('ledgers.budgets.index', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('ledgers/budgets/index')
            ->has('budgets', 0)
        );
});

test('deleting a tag soft deletes it', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $tag = Tag::factory()->for($ledger)->create(['name' => 'soft-delete-test']);

    $this->actingAs($user)
        ->delete(route('ledgers.tags.destroy', [$ledger, $tag]))
        ->assertRedirect();

    $this->assertSoftDeleted('tags', ['id' => $tag->id]);
});

test('soft deleted tag is not included in ledger tags relationship', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $activeTag = Tag::factory()->for($ledger)->create(['name' => 'active-tag']);
    $deletedTag = Tag::factory()->for($ledger)->create(['name' => 'deleted-tag']);
    $deletedTag->delete();

    $tags = $ledger->tags()->get();

    expect($tags)->toHaveCount(1);
    expect($tags->first()->id)->toBe($activeTag->id);
});
