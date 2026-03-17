<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;

test('rename category updates the name', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create(['name' => 'Groceries']);

    $response = $this
        ->actingAs($user)
        ->putJson(route('api.v1.ledgers.categories.update', [$ledger, $category]), [
            'name' => 'Food & Groceries',
            'transaction_type' => 'expense',
        ]);

    $response->assertOk();

    expect($category->fresh()->name)->toBe('Food & Groceries');
});

test('change category color updates the color', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create(['color' => '#ff0000']);

    $response = $this
        ->actingAs($user)
        ->putJson(route('api.v1.ledgers.categories.update', [$ledger, $category]), [
            'name' => $category->name,
            'color' => '#00ff00',
        ]);

    $response->assertOk();

    expect($category->fresh()->color)->toBe('#00ff00');
});

test('change subcategory parent via update', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $parentA = Category::factory()->for($ledger)->create(['name' => 'Parent A']);
    $parentB = Category::factory()->for($ledger)->create(['name' => 'Parent B']);
    $child = Category::factory()->for($ledger)->create([
        'name' => 'Child',
        'parent_id' => $parentA->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->putJson(route('api.v1.ledgers.categories.update', [$ledger, $child]), [
            'name' => $child->name,
            'parent_id' => $parentB->id,
        ]);

    $response->assertOk();

    expect($child->fresh()->parent_id)->toBe($parentB->id);
});

test('delete category with reassignment moves transactions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $account = Account::factory()->for($ledger)->create();
    $category = Category::factory()->for($ledger)->create();
    $targetCategory = Category::factory()->for($ledger)->create();

    $transaction = Transaction::factory()->for($ledger)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->deleteJson(route('api.v1.ledgers.categories.destroy', [$ledger, $category]), [
            'reassign_category_id' => $targetCategory->id,
        ]);

    $response->assertNoContent();

    expect($transaction->fresh()->category_id)->toBe($targetCategory->id)
        ->and(Category::find($category->id))->toBeNull();
});

test('delete category without reassignment sets transactions to uncategorized', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $account = Account::factory()->for($ledger)->create();
    $category = Category::factory()->for($ledger)->create();

    $transaction = Transaction::factory()->for($ledger)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->deleteJson(route('api.v1.ledgers.categories.destroy', [$ledger, $category]), [
            'reassign_category_id' => null,
        ]);

    $response->assertNoContent();

    expect($transaction->fresh()->category_id)->toBeNull()
        ->and(Category::find($category->id))->toBeNull();
});

test('delete parent category reassigns children transactions too', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $account = Account::factory()->for($ledger)->create();
    $parent = Category::factory()->for($ledger)->create();
    $child = Category::factory()->for($ledger)->create(['parent_id' => $parent->id]);
    $targetCategory = Category::factory()->for($ledger)->create();

    $parentTransaction = Transaction::factory()->for($ledger)->create([
        'account_id' => $account->id,
        'category_id' => $parent->id,
    ]);

    $childTransaction = Transaction::factory()->for($ledger)->create([
        'account_id' => $account->id,
        'category_id' => $child->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->deleteJson(route('api.v1.ledgers.categories.destroy', [$ledger, $parent]), [
            'reassign_category_id' => $targetCategory->id,
        ]);

    $response->assertNoContent();

    expect($parentTransaction->fresh()->category_id)->toBe($targetCategory->id)
        ->and($childTransaction->fresh()->category_id)->toBe($targetCategory->id)
        ->and(Category::find($parent->id))->toBeNull()
        ->and(Category::find($child->id))->toBeNull();
});

test('delete rejects reassignment to category from another ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create();
    $foreignLedger = Ledger::factory()->create();
    $foreignCategory = Category::factory()->for($foreignLedger)->create();

    $response = $this
        ->actingAs($user)
        ->deleteJson(route('api.v1.ledgers.categories.destroy', [$ledger, $category]), [
            'reassign_category_id' => $foreignCategory->id,
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors('reassign_category_id');
});

test('delete rejects reassignment to the same category being deleted', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create();

    $response = $this
        ->actingAs($user)
        ->deleteJson(route('api.v1.ledgers.categories.destroy', [$ledger, $category]), [
            'reassign_category_id' => $category->id,
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors('reassign_category_id');
});

test('category index page renders successfully', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.categories.index', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/categories/index')
    );
});
