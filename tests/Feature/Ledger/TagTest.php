<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('tag store does not create duplicate tags for the same ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->from(route('ledgers.tags.index', $ledger))
        ->post(route('ledgers.tags.store', $ledger), ['name' => 'dining']);

    $this->actingAs($user)
        ->from(route('ledgers.tags.index', $ledger))
        ->post(route('ledgers.tags.store', $ledger), ['name' => 'dining']);

    expect($ledger->tags()->where('name', 'dining')->count())->toBe(1);
});

test('tag index renders the inertia shell for api-driven tag data', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    Tag::factory()->for($ledger)->create(['name' => 'shell-tag']);

    $this->actingAs($user)
        ->get(route('ledgers.tags.index', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/tags/index')
            ->where('currentLedger.id', $ledger->id)
            ->missing('tags')
        );

    $this->actingAs($user)
        ->get(route('ledgers.tags.index', $ledger))
        ->assertViewMissing('page.deferredProps');
});

test('tag can be created through web routes', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.tags.index', $ledger))
        ->post(route('ledgers.tags.store', $ledger), [
            'name' => 'groceries',
            'color' => '#4ade80',
        ]);

    $response->assertRedirect(route('ledgers.tags.index', $ledger))
        ->assertSessionHasNoErrors();

    expect($ledger->tags()->where('name', 'groceries')->exists())->toBeTrue();
});

test('tag web create uses shared tag request validation messages', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.tags.index', $ledger))
        ->post(route('ledgers.tags.store', $ledger), [
            'name' => '',
            'color' => 'not-a-color',
        ]);

    $response->assertRedirect(route('ledgers.tags.index', $ledger))
        ->assertSessionHasErrors([
            'name' => 'Please enter a tag name.',
            'color' => 'Please enter a valid hex color like #FF0000.',
        ]);
});

test('tag web create uses correct redirect and flash under shared actions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->from(route('ledgers.tags.index', $ledger))
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->post(route('ledgers.tags.store', $ledger), [
            'name' => 'shared-create',
            'color' => '#22c55e',
        ]);

    $response->assertRedirect(route('ledgers.tags.index', $ledger))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Tag created.');
});

test('tag web update rejects duplicate names within the same ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    Tag::factory()->for($ledger)->create(['name' => 'existing-tag']);
    $tag = Tag::factory()->for($ledger)->create(['name' => 'editable-tag']);

    $response = $this->actingAs($user)
        ->from(route('ledgers.tags.index', $ledger))
        ->patch(route('ledgers.tags.update', [$ledger, $tag]), [
            'name' => 'existing-tag',
            'color' => '#22c55e',
        ]);

    $response->assertRedirect(route('ledgers.tags.index', $ledger))
        ->assertSessionHasErrors(['name']);

    expect($tag->fresh()->name)->toBe('editable-tag');
});

test('tag can be updated through web routes', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $tag = Tag::factory()->for($ledger)->create(['name' => 'old-tag']);

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.tags.index', $ledger))
        ->patch(route('ledgers.tags.update', [$ledger, $tag]), [
            'name' => 'new-tag',
            'color' => '#22c55e',
        ]);

    $response->assertRedirect(route('ledgers.tags.index', $ledger))
        ->assertSessionHasNoErrors();

    expect($tag->fresh()->name)->toBe('new-tag');
});

test('tag web update uses correct redirect and flash under shared actions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $tag = Tag::factory()->for($ledger)->create(['name' => 'before-update']);

    $response = $this->actingAs($user)
        ->from(route('ledgers.tags.index', $ledger))
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->patch(route('ledgers.tags.update', [$ledger, $tag]), [
            'name' => 'after-update',
            'color' => '#3b82f6',
        ]);

    $response->assertRedirect(route('ledgers.tags.index', $ledger))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Tag updated.');
});

test('tag can be deleted through web routes', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $tag = Tag::factory()->for($ledger)->create(['name' => 'to-delete']);

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.tags.index', $ledger))
        ->delete(route('ledgers.tags.destroy', [$ledger, $tag]));

    $response->assertRedirect(route('ledgers.tags.index', $ledger));

    expect($ledger->tags()->where('name', 'to-delete')->exists())->toBeFalse();
});

test('tag web delete uses correct redirect and flash under shared actions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $tag = Tag::factory()->for($ledger)->create(['name' => 'delete-shared']);

    $response = $this->actingAs($user)
        ->from(route('ledgers.tags.index', $ledger))
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->delete(route('ledgers.tags.destroy', [$ledger, $tag]));

    $response->assertRedirect(route('ledgers.tags.index', $ledger))
        ->assertSessionHas('success', 'Tag deleted.');
});

test('tag web routes continue to enforce ledger authorization through shared actions', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();
    $tag = Tag::factory()->for($ledger)->create();

    $this->actingAs($outsider)
        ->get(route('ledgers.tags.index', $ledger))
        ->assertForbidden();

    $this->actingAs($outsider)
        ->post(route('ledgers.tags.store', $ledger), [
            'name' => 'forbidden-create',
            'color' => '#22c55e',
        ])
        ->assertForbidden();

    $this->actingAs($outsider)
        ->patch(route('ledgers.tags.update', [$ledger, $tag]), [
            'name' => 'forbidden-update',
            'color' => '#3b82f6',
        ])
        ->assertForbidden();

    $this->actingAs($outsider)
        ->delete(route('ledgers.tags.destroy', [$ledger, $tag]))
        ->assertForbidden();
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

test('transaction index preserves tag filter in shell props', function () {
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
            'tag_ids' => [$tag->id],
        ]));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('filters.tag_ids', [(string) $tag->id])
        ->missing('transactions')
    );

    $response->assertViewMissing('page.deferredProps');
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
        ->from(route('ledgers.transactions.edit', [$ledger, $transaction]))
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

test('tag index page keeps only shell props for api-backed reads', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    Tag::factory()->for($ledger)->create([
        'name' => 'travel',
        'color' => '#4ade80',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.tags.index', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/tags/index')
        ->where('currentLedger.id', $ledger->id)
        ->missing('tags')
    );

    $response->assertViewMissing('page.deferredProps');
});

test('ledger tag web routes are available for inertia actions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $tag = Tag::factory()->for($ledger)->create();

    expect(parse_url(route('ledgers.tags.store', $ledger), PHP_URL_PATH))->toBe("/ledgers/{$ledger->id}/tags")
        ->and(parse_url(route('ledgers.tags.update', [$ledger, $tag]), PHP_URL_PATH))->toBe("/ledgers/{$ledger->id}/tags/{$tag->id}")
        ->and(parse_url(route('ledgers.tags.destroy', [$ledger, $tag]), PHP_URL_PATH))->toBe("/ledgers/{$ledger->id}/tags/{$tag->id}");
});
