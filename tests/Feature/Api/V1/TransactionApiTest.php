<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->ledger = Ledger::factory()->for($this->user)->create();
    $this->accountType = AccountType::factory()->for($this->ledger)->create();
    $this->account = Account::factory()->for($this->ledger)->for($this->accountType)->create();
    $this->category = Category::factory()->for($this->ledger)->create();
    $this->payee = Payee::factory()->for($this->ledger)->create();
    $this->token = $this->user->createToken('test');
});

test('transaction index returns paginated transactions', function () {
    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($this->category)
        ->count(3)
        ->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/transactions");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'ledger_id',
                    'account_id',
                    'category_id',
                    'transaction_type',
                    'amount',
                    'description',
                    'transaction_date',
                    'account',
                    'category',
                    'tags',
                    'created_at',
                    'updated_at',
                ],
            ],
            'links',
            'meta',
        ])
        ->assertJsonCount(3, 'data');
});

test('transaction show returns a single transaction', function () {
    $transaction = Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($this->category)
        ->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/transactions/{$transaction->id}");

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $transaction->id)
        ->assertJsonPath('data.description', $transaction->description);
});

test('transaction store creates a new expense', function () {
    $data = [
        'account_id' => $this->account->id,
        'category_id' => $this->category->id,
        'payee_id' => $this->payee->id,
        'transaction_type' => 'expense',
        'amount' => 50.00,
        'description' => 'Test expense',
        'transaction_date' => '2026-03-15',
    ];

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/transactions", $data);

    $response->assertCreated()
        ->assertJsonPath('data.description', 'Test expense');

    $this->assertDatabaseHas('transactions', [
        'ledger_id' => $this->ledger->id,
        'description' => 'Test expense',
    ]);
});

test('transaction store validates required fields', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/transactions", []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['account_id', 'transaction_type', 'amount', 'transaction_date']);
});

test('transaction update modifies an existing transaction', function () {
    $transaction = Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($this->category)
        ->expense()
        ->create(['description' => 'Original']);

    $data = [
        'account_id' => $this->account->id,
        'category_id' => $this->category->id,
        'transaction_type' => 'expense',
        'amount' => 75.00,
        'description' => 'Updated',
        'transaction_date' => '2026-03-15',
    ];

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->putJson("/api/v1/ledgers/{$this->ledger->id}/transactions/{$transaction->id}", $data);

    $response->assertSuccessful()
        ->assertJsonPath('data.description', 'Updated');
});

test('transaction destroy deletes a transaction', function () {
    $transaction = Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($this->category)
        ->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->deleteJson("/api/v1/ledgers/{$this->ledger->id}/transactions/{$transaction->id}");

    $response->assertNoContent();

    expect(Transaction::find($transaction->id))->toBeNull();
});

test('transaction index supports filtering by account', function () {
    $otherAccount = Account::factory()->for($this->ledger)->for($this->accountType)->create();

    Transaction::factory()->for($this->ledger)->for($this->account)->for($this->category)->create();
    Transaction::factory()->for($this->ledger)->for($otherAccount)->for($this->category)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/transactions?account_id={$this->account->id}");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});
