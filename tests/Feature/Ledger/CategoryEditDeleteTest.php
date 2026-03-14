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
        ->put(route('ledgers.categories.update', [$ledger, $category]), [
            'name' => 'Food & Groceries',
            'transaction_type' => 'expense',
        ]);

    $response->assertRedirect();

    expect($category->fresh()->name)->toBe('Food & Groceries');
});

test('change category color updates the color', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create(['color' => '#ff0000']);

    $response = $this
        ->actingAs($user)
        ->put(route('ledgers.categories.update', [$ledger, $category]), [
            'name' => $category->name,
            'color' => '#00ff00',
        ]);

    $response->assertRedirect();

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
        ->put(route('ledgers.categories.update', [$ledger, $child]), [
            'name' => $child->name,
            'parent_id' => $parentB->id,
        ]);

    $response->assertRedirect();

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
        ->delete(route('ledgers.categories.destroy', [$ledger, $category]), [
            'reassign_category_id' => $targetCategory->id,
        ]);

    $response->assertRedirect();

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
        ->delete(route('ledgers.categories.destroy', [$ledger, $category]), [
            'reassign_category_id' => null,
        ]);

    $response->assertRedirect();

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
        ->delete(route('ledgers.categories.destroy', [$ledger, $parent]), [
            'reassign_category_id' => $targetCategory->id,
        ]);

    $response->assertRedirect();

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
        ->delete(route('ledgers.categories.destroy', [$ledger, $category]), [
            'reassign_category_id' => $foreignCategory->id,
        ]);

    $response->assertSessionHasErrors('reassign_category_id');
});

test('delete rejects reassignment to the same category being deleted', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('ledgers.categories.destroy', [$ledger, $category]), [
            'reassign_category_id' => $category->id,
        ]);

    $response->assertSessionHasErrors('reassign_category_id');
});

test('category index includes transaction counts', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $account = Account::factory()->for($ledger)->create();
    $category = Category::factory()->for($ledger)->create(['parent_id' => null, 'position' => 1]);

    Transaction::factory()->count(3)->for($ledger)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.categories.index', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/categories/index')
        ->has('categories')
        ->where('categories.0.transactions_count', 3)
    );
});
