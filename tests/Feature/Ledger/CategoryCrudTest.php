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
        ->from(route('ledgers.categories.index', $ledger))
        ->delete(route('ledgers.categories.destroy', [$ledger, $category]));

    $response->assertRedirect(route('ledgers.categories.index', $ledger))
        ->assertSessionHasErrors('category');

    expect(Category::find($category->id))->not->toBeNull();
});

test('category destroy rejects deletion when category has children', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $parent = Category::factory()->for($ledger)->create(['name' => 'Parent']);
    Category::factory()->for($ledger)->create(['parent_id' => $parent->id, 'name' => 'Child']);

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.categories.index', $ledger))
        ->delete(route('ledgers.categories.destroy', [$ledger, $parent]));

    $response->assertRedirect(route('ledgers.categories.index', $ledger))
        ->assertSessionHasErrors('category');

    expect(Category::find($parent->id))->not->toBeNull();
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
        ->patch(route('ledgers.categories.update', [$ledger, $category]), [
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
        ->from(route('ledgers.categories.index', $ledger))
        ->post(route('ledgers.categories.store', $ledger), [
            'name' => 'New Category',
            'transaction_type' => 'expense',
        ]);

    $response->assertRedirect(route('ledgers.categories.index', $ledger))
        ->assertSessionHasNoErrors();

    $newCat = $ledger->categories()->where('name', 'New Category')->first();
    expect($newCat)->not->toBeNull()
        ->and($newCat->position)->toBe(2); // 1 existing + 1
});
