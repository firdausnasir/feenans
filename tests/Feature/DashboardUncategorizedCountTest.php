<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->ledger = Ledger::factory()->for($this->user)->create(['cycle_start_day' => 1]);
    $this->accountType = AccountType::factory()->for($this->ledger)->create();
    $this->account = Account::factory()->for($this->ledger)->for($this->accountType)->create();
    $this->category = Category::factory()->for($this->ledger)->create();
});

test('uncategorized count returns 0 when all transactions have categories', function () {
    Transaction::factory()->for($this->ledger)->for($this->account)->for($this->category)->expense()->create([
        'transaction_date' => now(),
    ]);

    Transaction::factory()->for($this->ledger)->for($this->account)->for($this->category)->income()->create([
        'transaction_date' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('ledgers.dashboard', $this->ledger))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('uncategorizedCount', 0)
            )
        );
});

test('uncategorized count returns correct count when some transactions lack categories', function () {
    // Categorized transaction
    Transaction::factory()->for($this->ledger)->for($this->account)->for($this->category)->expense()->create([
        'transaction_date' => now(),
    ]);

    // Uncategorized transactions
    Transaction::factory()->for($this->ledger)->for($this->account)->expense()->create([
        'category_id' => null,
        'transaction_date' => now(),
    ]);

    Transaction::factory()->for($this->ledger)->for($this->account)->income()->create([
        'category_id' => null,
        'transaction_date' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('ledgers.dashboard', $this->ledger))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('uncategorizedCount', 2)
            )
        );
});

test('transfer transactions are excluded from the uncategorized count', function () {
    // Uncategorized expense (should count)
    Transaction::factory()->for($this->ledger)->for($this->account)->expense()->create([
        'category_id' => null,
        'transaction_date' => now(),
    ]);

    // Transfer without category (should NOT count)
    Transaction::factory()->for($this->ledger)->for($this->account)->transferOut()->create([
        'transaction_date' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('ledgers.dashboard', $this->ledger))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('uncategorizedCount', 1)
            )
        );
});
