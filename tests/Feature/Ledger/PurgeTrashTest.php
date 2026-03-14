<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;

test('purge trash permanently deletes items older than 30 days', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $oldTransaction = Transaction::factory()->for($ledger)->for($account)->create();
    $oldTransaction->delete();
    Transaction::withTrashed()->where('id', $oldTransaction->id)->update([
        'deleted_at' => now()->subDays(31),
    ]);

    $recentTransaction = Transaction::factory()->for($ledger)->for($account)->create();
    $recentTransaction->delete();

    $this->artisan('trash:purge')
        ->assertSuccessful();

    expect(Transaction::withTrashed()->find($oldTransaction->id))->toBeNull();
    expect(Transaction::withTrashed()->find($recentTransaction->id))->not->toBeNull();
});

test('purge trash respects custom days option', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $tag = Tag::factory()->for($ledger)->create();
    $tag->delete();
    Tag::withTrashed()->where('id', $tag->id)->update([
        'deleted_at' => now()->subDays(8),
    ]);

    $this->artisan('trash:purge --days=7')
        ->assertSuccessful();

    expect(Tag::withTrashed()->find($tag->id))->toBeNull();
});

test('purge trash does not delete non-trashed items', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $category = Category::factory()->for($ledger)->create();

    $this->artisan('trash:purge')
        ->assertSuccessful();

    expect(Category::find($category->id))->not->toBeNull();
});

test('purge trash handles multiple model types', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $category = Category::factory()->for($ledger)->create();
    $category->delete();
    Category::withTrashed()->where('id', $category->id)->update([
        'deleted_at' => now()->subDays(31),
    ]);

    $tag = Tag::factory()->for($ledger)->create();
    $tag->delete();
    Tag::withTrashed()->where('id', $tag->id)->update([
        'deleted_at' => now()->subDays(31),
    ]);

    $payee = Payee::factory()->for($ledger)->create();
    $payee->delete();
    Payee::withTrashed()->where('id', $payee->id)->update([
        'deleted_at' => now()->subDays(31),
    ]);

    $this->artisan('trash:purge')
        ->assertSuccessful();

    expect(Category::withTrashed()->find($category->id))->toBeNull();
    expect(Tag::withTrashed()->find($tag->id))->toBeNull();
    expect(Payee::withTrashed()->find($payee->id))->toBeNull();
});

test('purge trash outputs correct message when nothing to purge', function () {
    $this->artisan('trash:purge')
        ->expectsOutput('No trashed items to purge.')
        ->assertSuccessful();
});
