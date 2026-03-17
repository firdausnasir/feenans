<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->ledger = Ledger::factory()->for($this->user)->create();
    $this->accountType = AccountType::factory()->for($this->ledger)->create();
    $this->token = $this->user->createToken('test');
});

test('it lists categories with transaction counts', function () {
    $category = Category::factory()->for($this->ledger)->create();
    $account = Account::factory()->for($this->ledger)->for($this->accountType)->create();
    Transaction::factory()->for($this->ledger)->for($account)->for($category)->count(3)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/categories?with_counts=1");

    $response->assertSuccessful()
        ->assertJsonPath('data.0.transactions_count', 3);
});

test('it lists categories as flat list', function () {
    $parent = Category::factory()->for($this->ledger)->create();
    Category::factory()->for($this->ledger)->create(['parent_id' => $parent->id]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/categories?flat=1");

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('it filters categories by transaction type', function () {
    Category::factory()->for($this->ledger)->create(['transaction_type' => 'expense']);
    Category::factory()->for($this->ledger)->create(['transaction_type' => 'income']);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/categories?transaction_type=expense&flat=1");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.transaction_type', 'expense');
});

test('it creates a category with validation', function () {
    $data = [
        'name' => 'Groceries',
        'transaction_type' => 'expense',
        'color' => '#ff0000',
        'icon' => 'cart',
    ];

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/categories", $data);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Groceries')
        ->assertJsonPath('data.transaction_type', 'expense');

    $this->assertDatabaseHas('categories', [
        'ledger_id' => $this->ledger->id,
        'name' => 'Groceries',
    ]);
});

test('it returns 422 for invalid category data', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/categories", []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'transaction_type']);
});

test('it updates a category', function () {
    $category = Category::factory()->for($this->ledger)->create(['name' => 'Old Name']);

    $data = [
        'name' => 'New Name',
        'transaction_type' => 'expense',
    ];

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->putJson("/api/v1/ledgers/{$this->ledger->id}/categories/{$category->id}", $data);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'New Name');
});

test('it deletes a category', function () {
    $category = Category::factory()->for($this->ledger)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->deleteJson("/api/v1/ledgers/{$this->ledger->id}/categories/{$category->id}");

    $response->assertNoContent();

    expect(Category::find($category->id))->toBeNull();
});

test('it reassigns transactions on category delete', function () {
    $category = Category::factory()->for($this->ledger)->create();
    $targetCategory = Category::factory()->for($this->ledger)->create();
    $account = Account::factory()->for($this->ledger)->for($this->accountType)->create();
    $transaction = Transaction::factory()->for($this->ledger)->for($account)->for($category)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->deleteJson("/api/v1/ledgers/{$this->ledger->id}/categories/{$category->id}", [
            'reassign_category_id' => $targetCategory->id,
        ]);

    $response->assertNoContent();

    expect(Transaction::find($transaction->id)->category_id)->toBe($targetCategory->id);
    expect(Category::find($category->id))->toBeNull();
});

test('it rejects deletion of category with transactions without reassignment', function () {
    $category = Category::factory()->for($this->ledger)->create();
    $account = Account::factory()->for($this->ledger)->for($this->accountType)->create();
    Transaction::factory()->for($this->ledger)->for($account)->for($category)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->deleteJson("/api/v1/ledgers/{$this->ledger->id}/categories/{$category->id}");

    $response->assertUnprocessable();
});

test('it reorders categories', function () {
    $cat1 = Category::factory()->for($this->ledger)->create(['position' => 1]);
    $cat2 = Category::factory()->for($this->ledger)->create(['position' => 2]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/categories/reorder", [
            'items' => [
                ['id' => $cat1->id, 'position' => 2],
                ['id' => $cat2->id, 'position' => 1],
            ],
        ]);

    $response->assertSuccessful();

    expect(Category::find($cat1->id)->position)->toBe(2);
    expect(Category::find($cat2->id)->position)->toBe(1);
});

test('it returns 401 when unauthenticated for categories', function () {
    $response = $this->getJson("/api/v1/ledgers/{$this->ledger->id}/categories");

    $response->assertUnauthorized();
});
