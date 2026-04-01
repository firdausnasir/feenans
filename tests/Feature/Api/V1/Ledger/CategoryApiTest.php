<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('token authenticated client can list category hierarchy for a ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $parent = Category::factory()->for($ledger)->create([
        'name' => 'Food',
        'parent_id' => null,
        'transaction_type' => 'expense',
        'color' => '#22c55e',
        'icon' => 'utensils',
        'position' => 1,
    ]);

    Category::factory()->for($ledger)->create([
        'name' => 'Restaurants',
        'parent_id' => $parent->id,
        'transaction_type' => 'expense',
        'color' => '#16a34a',
        'icon' => 'fork-knife',
        'position' => 1,
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson(route('api.v1.ledgers.categories.index', $ledger));

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $parent->id)
        ->assertJsonPath('data.0.ledger_id', $ledger->id)
        ->assertJsonPath('data.0.parent_id', null)
        ->assertJsonPath('data.0.name', 'Food')
        ->assertJsonPath('data.0.transaction_type', 'expense')
        ->assertJsonPath('data.0.color', '#22c55e')
        ->assertJsonPath('data.0.icon', 'utensils')
        ->assertJsonPath('data.0.position', 1)
        ->assertJsonPath('data.0.transactions_count', 0)
        ->assertJsonPath('data.0.children.0.parent_id', $parent->id)
        ->assertJsonPath('data.0.children.0.name', 'Restaurants')
        ->assertJsonPath('data.0.children.0.transaction_type', 'expense')
        ->assertJsonPath('data.0.children.0.color', '#16a34a')
        ->assertJsonPath('data.0.children.0.icon', 'fork-knife')
        ->assertJsonPath('data.0.children.0.transactions_count', 0);
});

test('category api create returns validation errors as json', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson(route('api.v1.ledgers.categories.store', $ledger), [
        'name' => '',
        'transaction_type' => '',
        'color' => 'invalid-color',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'transaction_type'])
        ->assertJsonMissingValidationErrors(['color']);
});

test('category api create returns created category contract', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson(route('api.v1.ledgers.categories.store', $ledger), [
        'name' => 'Travel',
        'transaction_type' => 'expense',
        'color' => '#22c55e',
        'icon' => 'plane',
        'parent_id' => null,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.ledger_id', $ledger->id)
        ->assertJsonPath('data.parent_id', null)
        ->assertJsonPath('data.name', 'Travel')
        ->assertJsonPath('data.transaction_type', 'expense')
        ->assertJsonPath('data.color', '#22c55e')
        ->assertJsonPath('data.icon', 'plane')
        ->assertJsonPath('data.position', 1)
        ->assertJsonPath('data.transactions_count', 0)
        ->assertJsonPath('data.created_at', fn (mixed $createdAt): bool => is_string($createdAt) && $createdAt !== '')
        ->assertJsonPath('data.updated_at', fn (mixed $updatedAt): bool => is_string($updatedAt) && $updatedAt !== '');
});

test('category api update returns updated category json', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $parent = Category::factory()->for($ledger)->create(['transaction_type' => 'expense']);
    $category = Category::factory()->for($ledger)->create([
        'name' => 'Old Name',
        'transaction_type' => 'expense',
        'color' => '#6366f1',
        'icon' => 'old',
        'parent_id' => null,
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->patchJson(route('api.v1.ledgers.categories.update', [$ledger, $category]), [
        'name' => 'New Name',
        'transaction_type' => 'expense',
        'color' => '#22c55e',
        'icon' => 'new',
        'parent_id' => $parent->id,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $category->id)
        ->assertJsonPath('data.ledger_id', $ledger->id)
        ->assertJsonPath('data.parent_id', $parent->id)
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.transaction_type', 'expense')
        ->assertJsonPath('data.color', '#22c55e')
        ->assertJsonPath('data.icon', 'new');
});

test('category api update keeps parent category children ordered by position in response when subcategories still exist', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $parent = Category::factory()->for($ledger)->create([
        'name' => 'Housing',
        'transaction_type' => 'expense',
    ]);
    $laterChild = Category::factory()->for($ledger)->create([
        'parent_id' => $parent->id,
        'name' => 'Rent',
        'transaction_type' => 'expense',
        'position' => 2,
    ]);
    $earlierChild = Category::factory()->for($ledger)->create([
        'parent_id' => $parent->id,
        'name' => 'Utilities',
        'transaction_type' => 'expense',
        'position' => 1,
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->patchJson(route('api.v1.ledgers.categories.update', [$ledger, $parent]), [
        'name' => 'Home',
        'transaction_type' => 'expense',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $parent->id)
        ->assertJsonPath('data.name', 'Home')
        ->assertJsonPath('data.children.0.id', $earlierChild->id)
        ->assertJsonPath('data.children.0.parent_id', $parent->id)
        ->assertJsonPath('data.children.0.name', 'Utilities')
        ->assertJsonPath('data.children.1.id', $laterChild->id)
        ->assertJsonPath('data.children.1.parent_id', $parent->id)
        ->assertJsonPath('data.children.1.name', 'Rent');
});

test('category api update rejects parent from another ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create(['transaction_type' => 'expense']);
    $foreignLedger = Ledger::factory()->create();
    $foreignParent = Category::factory()->for($foreignLedger)->create(['transaction_type' => 'expense']);

    Sanctum::actingAs($user, ['*']);

    $response = $this->patchJson(route('api.v1.ledgers.categories.update', [$ledger, $category]), [
        'name' => 'New Name',
        'transaction_type' => 'expense',
        'parent_id' => $foreignParent->id,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);
});

test('category api create rejects using a child category as parent', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $root = Category::factory()->for($ledger)->create(['transaction_type' => 'expense']);
    $child = Category::factory()->for($ledger)->create([
        'parent_id' => $root->id,
        'transaction_type' => 'expense',
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson(route('api.v1.ledgers.categories.store', $ledger), [
        'name' => 'Invalid Child Parent',
        'transaction_type' => 'expense',
        'parent_id' => $child->id,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);
});

test('category api create rejects parent with mismatched transaction type', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $incomeParent = Category::factory()->for($ledger)->create(['transaction_type' => 'income']);

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson(route('api.v1.ledgers.categories.store', $ledger), [
        'name' => 'Expense Child',
        'transaction_type' => 'expense',
        'parent_id' => $incomeParent->id,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);
});

test('category api update rejects self parent', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create(['transaction_type' => 'expense']);

    Sanctum::actingAs($user, ['*']);

    $response = $this->patchJson(route('api.v1.ledgers.categories.update', [$ledger, $category]), [
        'name' => $category->name,
        'transaction_type' => 'expense',
        'parent_id' => $category->id,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);
});

test('category api update rejects moving a category under its descendant', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $parent = Category::factory()->for($ledger)->create(['transaction_type' => 'expense']);
    $child = Category::factory()->for($ledger)->create([
        'parent_id' => $parent->id,
        'transaction_type' => 'expense',
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->patchJson(route('api.v1.ledgers.categories.update', [$ledger, $parent]), [
        'name' => $parent->name,
        'transaction_type' => 'expense',
        'parent_id' => $child->id,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);
});

test('category api update rejects assigning a parent to a category that already has children', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $newParent = Category::factory()->for($ledger)->create(['transaction_type' => 'expense']);
    $category = Category::factory()->for($ledger)->create(['transaction_type' => 'expense']);
    Category::factory()->for($ledger)->create([
        'parent_id' => $category->id,
        'transaction_type' => 'expense',
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->patchJson(route('api.v1.ledgers.categories.update', [$ledger, $category]), [
        'name' => $category->name,
        'transaction_type' => 'expense',
        'parent_id' => $newParent->id,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);
});

test('category api update rejects changing a child category transaction type without resubmitting parent', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $parent = Category::factory()->for($ledger)->create(['transaction_type' => 'expense']);
    $child = Category::factory()->for($ledger)->create([
        'parent_id' => $parent->id,
        'transaction_type' => 'expense',
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->patchJson(route('api.v1.ledgers.categories.update', [$ledger, $child]), [
        'name' => $child->name,
        'transaction_type' => 'income',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['transaction_type']);
});

test('category api update rejects changing a parent category transaction type when children would mismatch', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $parent = Category::factory()->for($ledger)->create(['transaction_type' => 'expense']);
    Category::factory()->for($ledger)->create([
        'parent_id' => $parent->id,
        'transaction_type' => 'expense',
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->patchJson(route('api.v1.ledgers.categories.update', [$ledger, $parent]), [
        'name' => $parent->name,
        'transaction_type' => 'income',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['transaction_type']);
});

test('category api delete can uncategorize transactions when reassign_category_id is null', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $account = Account::factory()->for($ledger)->create();
    $category = Category::factory()->for($ledger)->create();

    $transaction = Transaction::factory()->for($ledger)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->deleteJson(route('api.v1.ledgers.categories.destroy', [$ledger, $category]), [
        'reassign_category_id' => null,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $category->id)
        ->assertJsonPath('data.name', $category->name);

    expect($transaction->fresh()->category_id)->toBeNull();
});

test('category api delete can reassign transactions to another category', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $account = Account::factory()->for($ledger)->create();
    $category = Category::factory()->for($ledger)->create(['transaction_type' => 'expense']);
    $targetCategory = Category::factory()->for($ledger)->create(['transaction_type' => 'expense']);

    $transaction = Transaction::factory()->for($ledger)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->deleteJson(route('api.v1.ledgers.categories.destroy', [$ledger, $category]), [
        'reassign_category_id' => $targetCategory->id,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $category->id)
        ->assertJsonPath('data.name', $category->name);

    expect($transaction->fresh()->category_id)->toBe($targetCategory->id);
});

test('category api delete keeps parent category children ordered by position in response before deletion', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $parent = Category::factory()->for($ledger)->create([
        'name' => 'Housing',
        'transaction_type' => 'expense',
    ]);
    $laterChild = Category::factory()->for($ledger)->create([
        'parent_id' => $parent->id,
        'name' => 'Rent',
        'transaction_type' => 'expense',
        'position' => 2,
    ]);
    $earlierChild = Category::factory()->for($ledger)->create([
        'parent_id' => $parent->id,
        'name' => 'Utilities',
        'transaction_type' => 'expense',
        'position' => 1,
    ]);
    $targetCategory = Category::factory()->for($ledger)->create([
        'transaction_type' => 'expense',
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->deleteJson(route('api.v1.ledgers.categories.destroy', [$ledger, $parent]), [
        'reassign_category_id' => $targetCategory->id,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $parent->id)
        ->assertJsonPath('data.children.0.id', $earlierChild->id)
        ->assertJsonPath('data.children.0.parent_id', $parent->id)
        ->assertJsonPath('data.children.0.name', 'Utilities')
        ->assertJsonPath('data.children.1.id', $laterChild->id)
        ->assertJsonPath('data.children.1.parent_id', $parent->id)
        ->assertJsonPath('data.children.1.name', 'Rent');
});

test('category api delete rejects invalid reassignment target from another ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create();
    $foreignLedger = Ledger::factory()->create();
    $foreignCategory = Category::factory()->for($foreignLedger)->create();

    Sanctum::actingAs($user, ['*']);

    $response = $this->deleteJson(route('api.v1.ledgers.categories.destroy', [$ledger, $category]), [
        'reassign_category_id' => $foreignCategory->id,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['reassign_category_id']);
});

test('category api delete rejects reassignment to a child of the category being deleted', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $account = Account::factory()->for($ledger)->create();
    $parent = Category::factory()->for($ledger)->create(['transaction_type' => 'expense']);
    $child = Category::factory()->for($ledger)->create([
        'parent_id' => $parent->id,
        'transaction_type' => 'expense',
    ]);

    $transaction = Transaction::factory()->for($ledger)->create([
        'account_id' => $account->id,
        'category_id' => $parent->id,
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->deleteJson(route('api.v1.ledgers.categories.destroy', [$ledger, $parent]), [
        'reassign_category_id' => $child->id,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['reassign_category_id']);

    expect($transaction->fresh()->category_id)->toBe($parent->id)
        ->and(Category::find($parent->id))->not->toBeNull()
        ->and(Category::find($child->id))->not->toBeNull();
});

test('category api reorder updates positions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $categoryA = Category::factory()->for($ledger)->create(['position' => 1]);
    $categoryB = Category::factory()->for($ledger)->create(['position' => 2]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson(route('api.v1.ledgers.categories.reorder', $ledger), [
        'items' => [
            ['id' => $categoryA->id, 'position' => 2],
            ['id' => $categoryB->id, 'position' => 1],
        ],
    ]);

    $response->assertSuccessful();

    expect($categoryA->fresh()->position)->toBe(2)
        ->and($categoryB->fresh()->position)->toBe(1);
});

test('category api reorder rejects ids from another ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create(['position' => 1]);
    $foreignLedger = Ledger::factory()->create();
    $foreignCategory = Category::factory()->for($foreignLedger)->create(['position' => 2]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson(route('api.v1.ledgers.categories.reorder', $ledger), [
        'items' => [
            ['id' => $category->id, 'position' => 2],
            ['id' => $foreignCategory->id, 'position' => 1],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['items.1.id']);
});

test('category api reorder rejects duplicate ids', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create(['position' => 1]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson(route('api.v1.ledgers.categories.reorder', $ledger), [
        'items' => [
            ['id' => $category->id, 'position' => 1],
            ['id' => $category->id, 'position' => 2],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['items.1.id']);
});

test('category api reorder rejects duplicate positions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $categoryA = Category::factory()->for($ledger)->create(['position' => 1]);
    $categoryB = Category::factory()->for($ledger)->create(['position' => 2]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson(route('api.v1.ledgers.categories.reorder', $ledger), [
        'items' => [
            ['id' => $categoryA->id, 'position' => 1],
            ['id' => $categoryB->id, 'position' => 1],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['items.1.position']);
});

test('category api returns json forbidden when ledger policy denies access', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();
    $category = Category::factory()->for($ledger)->create();

    Sanctum::actingAs($outsider, ['*']);

    $this->getJson(route('api.v1.ledgers.categories.index', $ledger))
        ->assertForbidden();

    $this->postJson(route('api.v1.ledgers.categories.store', $ledger), [
        'name' => 'Blocked',
        'transaction_type' => 'expense',
    ])->assertForbidden();

    $this->patchJson(route('api.v1.ledgers.categories.update', [$ledger, $category]), [
        'name' => 'Blocked',
        'transaction_type' => 'expense',
    ])->assertForbidden();

    $this->deleteJson(route('api.v1.ledgers.categories.destroy', [$ledger, $category]), [
        'reassign_category_id' => null,
    ])->assertForbidden();

    $this->postJson(route('api.v1.ledgers.categories.reorder', $ledger), [
        'items' => [
            ['id' => $category->id, 'position' => 1],
        ],
    ])->assertForbidden();
});

test('category api rejects guest requests', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create();

    $this->getJson(route('api.v1.ledgers.categories.index', $ledger))
        ->assertUnauthorized();

    $this->postJson(route('api.v1.ledgers.categories.store', $ledger), [
        'name' => 'Guest',
        'transaction_type' => 'expense',
    ])->assertUnauthorized();

    $this->patchJson(route('api.v1.ledgers.categories.update', [$ledger, $category]), [
        'name' => 'Guest',
        'transaction_type' => 'expense',
    ])->assertUnauthorized();

    $this->deleteJson(route('api.v1.ledgers.categories.destroy', [$ledger, $category]), [
        'reassign_category_id' => null,
    ])->assertUnauthorized();

    $this->postJson(route('api.v1.ledgers.categories.reorder', $ledger), [
        'items' => [
            ['id' => $category->id, 'position' => 1],
        ],
    ])->assertUnauthorized();
});
