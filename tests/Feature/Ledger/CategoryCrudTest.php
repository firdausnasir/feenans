<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;

test('category destroy rejects deletion when category has transactions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-10.00',
    ]);

    $response = $this
        ->actingAs($user)
        ->delete(route('ledgers.categories.destroy', [$ledger, $category]));

    $response->assertRedirect();
    $response->assertSessionHasErrors('category');

    expect(Category::find($category->id))->not->toBeNull();
});

test('category destroy rejects deletion when category has children', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $parent = Category::factory()->for($ledger)->create(['name' => 'Parent']);
    Category::factory()->for($ledger)->create(['parent_id' => $parent->id, 'name' => 'Child']);

    $response = $this
        ->actingAs($user)
        ->delete(route('ledgers.categories.destroy', [$ledger, $parent]));

    $response->assertRedirect();
    $response->assertSessionHasErrors('category');

    expect(Category::find($parent->id))->not->toBeNull();
});

test('category trash page shows deleted categories', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->trashed()->create(['name' => 'Deleted Category']);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.categories.trash', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/categories/trash/index')
        ->has('categories', 1)
    );
});

test('category restore restores a soft deleted category', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->trashed()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('ledgers.categories.restore', [$ledger, $category]));

    $response->assertRedirect(route('ledgers.categories.trash', $ledger));

    $this->assertNotSoftDeleted('categories', ['id' => $category->id]);
});

test('category force destroy permanently deletes a category', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->trashed()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('ledgers.categories.force-destroy', [$ledger, $category]));

    $response->assertRedirect(route('ledgers.categories.trash', $ledger));

    expect(Category::withTrashed()->find($category->id))->toBeNull();
});

test('category store is forbidden for another users ledger', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->post(route('ledgers.categories.store', $ledger), [
            'name' => 'Hacked',
            'transaction_type' => 'expense',
        ])
        ->assertForbidden();
});

test('category update is forbidden for another users ledger', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();
    $category = Category::factory()->for($ledger)->create();

    $this->actingAs($intruder)
        ->put(route('ledgers.categories.update', [$ledger, $category]), [
            'name' => 'Hacked',
            'transaction_type' => 'expense',
        ])
        ->assertForbidden();
});

test('category store assigns position based on existing count', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    Category::factory()->for($ledger)->create(['name' => 'Existing']);

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.categories.store', $ledger), [
            'name' => 'New Category',
            'transaction_type' => 'expense',
        ]);

    $response->assertRedirect();

    $newCat = $ledger->categories()->where('name', 'New Category')->first();
    expect($newCat)->not->toBeNull()
        ->and($newCat->position)->toBe(2); // 1 existing + 1
});
