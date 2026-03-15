<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;

test('users can create a tag in a ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.tags.store', $ledger), [
            'name' => 'groceries',
            'color' => '#4ade80',
        ]);

    $response->assertRedirect();

    expect($ledger->tags()->where('name', 'groceries')->exists())->toBeTrue();
});

test('tag store does not create duplicate tags for the same ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('ledgers.tags.store', $ledger), ['name' => 'dining']);

    $this->actingAs($user)
        ->post(route('ledgers.tags.store', $ledger), ['name' => 'dining']);

    expect($ledger->tags()->where('name', 'dining')->count())->toBe(1);
});

test('users can delete a tag', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $tag = Tag::factory()->for($ledger)->create(['name' => 'to-delete']);

    $response = $this
        ->actingAs($user)
        ->delete(route('ledgers.tags.destroy', [$ledger, $tag]));

    $response->assertRedirect();

    expect($ledger->tags()->where('name', 'to-delete')->exists())->toBeFalse();
});

test('transaction with tags syncs tags correctly on store', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $tag1 = Tag::factory()->for($ledger)->create(['name' => 'tag-one']);
    $tag2 = Tag::factory()->for($ledger)->create(['name' => 'tag-two']);

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $account->id,
            'transaction_type' => 'expense',
            'amount' => 10.00,
            'description' => 'Test',
            'transaction_date' => '2026-03-13',
            'tag_ids' => [$tag1->id, $tag2->id],
        ]);

    $response->assertRedirect();

    $transaction = $ledger->transactions()->first();
    expect($transaction->tags()->pluck('tags.id')->sort()->values()->toArray())
        ->toBe(collect([$tag1->id, $tag2->id])->sort()->values()->toArray());
});

test('transaction index can be filtered by tag', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $tag = Tag::factory()->for($ledger)->create(['name' => 'filter-tag']);

    $tagged = Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_date' => now()->toDateString(),
    ]);
    $tagged->tags()->sync([$tag->id]);

    $untagged = Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_date' => now()->toDateString(),
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.transactions.index', [
            'ledger' => $ledger,
            'tag_id' => $tag->id,
        ]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/transactions/index')
        ->where('transactions.data.0.id', $tagged->id)
        ->where('transactions.total', 1)
    );
});

test('transaction tags are synced on update', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();
    $tag1 = Tag::factory()->for($ledger)->create(['name' => 'old-tag']);
    $tag2 = Tag::factory()->for($ledger)->create(['name' => 'new-tag']);

    $transaction = Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'transaction_type' => 'expense',
        'amount' => -5.00,
        'transaction_date' => '2026-03-13',
    ]);
    $transaction->tags()->sync([$tag1->id]);

    $this->actingAs($user)
        ->put(route('ledgers.transactions.update', [$ledger, $transaction]), [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_type' => 'expense',
            'amount' => 5.00,
            'description' => 'Updated',
            'transaction_date' => '2026-03-13',
            'tag_ids' => [$tag2->id],
        ]);

    $transaction->refresh();
    expect($transaction->tags()->pluck('tags.id')->toArray())->toBe([$tag2->id]);
});
