<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('dashboard returns uncategorized_count of 0 when all transactions have categories', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    Transaction::factory()->for($ledger)->for($account)->for($category)->expense()->create([
        'transaction_date' => now(),
    ]);

    Transaction::factory()->for($ledger)->for($account)->for($category)->income()->create([
        'transaction_date' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger))
        ->assertInertia(fn (Assert $page) => $page
            ->where('uncategorizedCount', 0)
        );
});

test('dashboard returns correct uncategorized count when some transactions lack categories', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    // Categorized transaction
    Transaction::factory()->for($ledger)->for($account)->for($category)->expense()->create([
        'transaction_date' => now(),
    ]);

    // Uncategorized transactions
    Transaction::factory()->for($ledger)->for($account)->expense()->create([
        'category_id' => null,
        'transaction_date' => now(),
    ]);

    Transaction::factory()->for($ledger)->for($account)->income()->create([
        'category_id' => null,
        'transaction_date' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger))
        ->assertInertia(fn (Assert $page) => $page
            ->where('uncategorizedCount', 2)
        );
});

test('transfer transactions are excluded from the uncategorized count', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    // Uncategorized expense (should count)
    Transaction::factory()->for($ledger)->for($account)->expense()->create([
        'category_id' => null,
        'transaction_date' => now(),
    ]);

    // Transfer without category (should NOT count)
    Transaction::factory()->for($ledger)->for($account)->transferOut()->create([
        'transaction_date' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger))
        ->assertInertia(fn (Assert $page) => $page
            ->where('uncategorizedCount', 1)
        );
});
