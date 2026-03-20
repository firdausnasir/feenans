<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('store creates split transaction with splits', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $food = Category::factory()->for($ledger)->create(['transaction_type' => TransactionType::Expense]);
    $drinks = Category::factory()->for($ledger)->create(['transaction_type' => TransactionType::Expense]);

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $account->id,
            'transaction_type' => 'expense',
            'amount' => 100.00,
            'description' => 'Dinner receipt',
            'transaction_date' => '2026-03-13',
            'splits' => [
                [
                    'amount' => 60.00,
                    'category_id' => $food->id,
                    'description' => 'Food',
                ],
                [
                    'amount' => 40.00,
                    'category_id' => $drinks->id,
                    'description' => 'Drinks',
                ],
            ],
        ]);

    $response->assertRedirect();

    $transaction = $ledger->transactions()->latest('id')->first();

    expect($transaction)->not->toBeNull();
    expect($transaction->fresh()->splits)->toHaveCount(2)
        ->and((string) $transaction->fresh()->splits[0]->amount)->toBe('-60.00')
        ->and((string) $transaction->fresh()->splits[1]->amount)->toBe('-40.00');
});

test('store validates splits must sum to total amount', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create(['transaction_type' => TransactionType::Expense]);

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $account->id,
            'transaction_type' => 'expense',
            'amount' => 100.00,
            'description' => 'Dinner receipt',
            'transaction_date' => '2026-03-13',
            'splits' => [
                [
                    'amount' => 60.00,
                    'category_id' => $category->id,
                    'description' => 'Food',
                ],
                [
                    'amount' => 30.00,
                    'category_id' => $category->id,
                    'description' => 'Drinks',
                ],
            ],
        ]);

    $response->assertSessionHasErrors('splits');
});

test('store requires at least 2 splits when splits provided', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create(['transaction_type' => TransactionType::Expense]);

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $account->id,
            'transaction_type' => 'expense',
            'amount' => 100.00,
            'description' => 'Dinner receipt',
            'transaction_date' => '2026-03-13',
            'splits' => [
                [
                    'amount' => 100.00,
                    'category_id' => $category->id,
                    'description' => 'Everything',
                ],
            ],
        ]);

    $response->assertSessionHasErrors('splits');
});

test('update syncs splits correctly', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $food = Category::factory()->for($ledger)->create(['transaction_type' => TransactionType::Expense]);
    $drinks = Category::factory()->for($ledger)->create(['transaction_type' => TransactionType::Expense]);
    $dessert = Category::factory()->for($ledger)->create(['transaction_type' => TransactionType::Expense]);

    $transaction = Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-100.00',
        'transfer_pair_id' => null,
    ]);

    $transaction->splits()->createMany([
        [
            'category_id' => $food->id,
            'amount' => '-60.00',
            'description' => 'Food',
        ],
        [
            'category_id' => $drinks->id,
            'amount' => '-40.00',
            'description' => 'Drinks',
        ],
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.transactions.edit', [$ledger, $transaction]))
        ->put(route('ledgers.transactions.update', [$ledger, $transaction]), [
            'account_id' => $account->id,
            'category_id' => null,
            'payee_id' => null,
            'transaction_type' => 'expense',
            'amount' => 100.00,
            'description' => 'Dinner receipt',
            'notes' => null,
            'transaction_date' => '2026-03-13',
            'splits' => [
                [
                    'amount' => 70.00,
                    'category_id' => $food->id,
                    'description' => 'Food',
                ],
                [
                    'amount' => 20.00,
                    'category_id' => $drinks->id,
                    'description' => 'Drinks',
                ],
                [
                    'amount' => 10.00,
                    'category_id' => $dessert->id,
                    'description' => 'Dessert',
                ],
            ],
        ]);

    $response->assertRedirect(route('ledgers.transactions.index', $ledger))
        ->assertSessionHas('success', 'Transaction updated.');

    $transaction->refresh();

    expect($transaction->splits)->toHaveCount(3)
        ->and($transaction->splits->pluck('description')->all())->toBe(['Food', 'Drinks', 'Dessert']);
});

test('transaction with splits shows split metadata in deferred index response', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create(['transaction_type' => TransactionType::Expense]);

    $transaction = Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-100.00',
        'description' => 'Dinner receipt',
        'transaction_date' => '2026-03-13',
    ]);

    $transaction->splits()->createMany([
        [
            'category_id' => $category->id,
            'amount' => '-60.00',
            'description' => 'Food',
        ],
        [
            'category_id' => $category->id,
            'amount' => '-40.00',
            'description' => 'Drinks',
        ],
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.transactions.index', $ledger));

    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/transactions/index')
            ->missing('transactions')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('transactions.data', 1)
                ->where('transactions.data.0.splits_count', 2)
                ->where('transactions.data.0.description', 'Dinner receipt')
            )
        );
});

test('deleting transaction also removes its splits', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create(['transaction_type' => TransactionType::Expense]);

    $transaction = Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-100.00',
        'transfer_pair_id' => null,
    ]);

    $transaction->splits()->createMany([
        [
            'category_id' => $category->id,
            'amount' => '-60.00',
            'description' => 'Food',
        ],
        [
            'category_id' => $category->id,
            'amount' => '-40.00',
            'description' => 'Drinks',
        ],
    ]);

    expect($transaction->splits()->count())->toBe(2);

    $this
        ->actingAs($user)
        ->from(route('ledgers.transactions.edit', [$ledger, $transaction]))
        ->delete(route('ledgers.transactions.destroy', [$ledger, $transaction]))
        ->assertRedirect(route('ledgers.transactions.index', $ledger))
        ->assertSessionHas('success', 'Transaction deleted.');

    expect(Transaction::find($transaction->id))->toBeNull();
});
