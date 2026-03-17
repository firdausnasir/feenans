<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\Tag;
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

test('it lists transactions with array filters', function () {
    $otherAccount = Account::factory()->for($this->ledger)->for($this->accountType)->create();
    $otherCategory = Category::factory()->for($this->ledger)->create();
    $tag = Tag::factory()->for($this->ledger)->create();

    $t1 = Transaction::factory()->for($this->ledger)->for($this->account)->for($this->category)->expense()->create();
    $t2 = Transaction::factory()->for($this->ledger)->for($otherAccount)->for($otherCategory)->expense()->create();
    $t3 = Transaction::factory()->for($this->ledger)->for($this->account)->for($otherCategory)->expense()->create();
    $t1->tags()->attach($tag);

    // Filter by account_ids[]
    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/transactions?account_ids[]={$this->account->id}");

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');

    // Filter by category_ids[]
    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/transactions?category_ids[]={$this->category->id}");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');

    // Filter by tag_ids[]
    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/transactions?tag_ids[]={$tag->id}");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

test('it searches transactions by description and notes', function () {
    Transaction::factory()->for($this->ledger)->for($this->account)->for($this->category)->create([
        'description' => 'Grocery shopping',
        'notes' => 'Weekly groceries',
    ]);
    Transaction::factory()->for($this->ledger)->for($this->account)->for($this->category)->create([
        'description' => 'Rent payment',
        'notes' => 'Monthly rent',
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/transactions?search=grocery");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');

    // Search in notes
    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/transactions?search=rent");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

test('it filters uncategorized transactions', function () {
    Transaction::factory()->for($this->ledger)->for($this->account)->for($this->category)->expense()->create();
    Transaction::factory()->for($this->ledger)->for($this->account)->expense()->create(['category_id' => null]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/transactions?uncategorized=1");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

test('it includes splits and attachments in show', function () {
    $transaction = Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($this->category)
        ->expense()
        ->create();

    $transaction->splits()->create([
        'category_id' => $this->category->id,
        'amount' => -25.00,
        'description' => 'Split 1',
    ]);

    $transaction->splits()->create([
        'category_id' => $this->category->id,
        'amount' => -25.00,
        'description' => 'Split 2',
    ]);

    $transaction->attachments()->create([
        'filename' => 'receipt.pdf',
        'path' => 'attachments/test/receipt.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1024,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/transactions/{$transaction->id}");

    $response->assertSuccessful()
        ->assertJsonPath('data.is_split', true)
        ->assertJsonCount(2, 'data.splits')
        ->assertJsonCount(1, 'data.attachments')
        ->assertJsonStructure([
            'data' => [
                'splits' => [
                    '*' => ['id', 'transaction_id', 'category_id', 'amount', 'description'],
                ],
                'attachments' => [
                    '*' => ['id', 'transaction_id', 'filename', 'mime_type', 'size', 'url'],
                ],
            ],
        ]);
});

test('it bulk updates transactions', function () {
    $otherCategory = Category::factory()->for($this->ledger)->create();

    $t1 = Transaction::factory()->for($this->ledger)->for($this->account)->for($this->category)->expense()->create();
    $t2 = Transaction::factory()->for($this->ledger)->for($this->account)->for($this->category)->expense()->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/transactions/bulk-update", [
            'ids' => [$t1->id, $t2->id],
            'action' => 'change_category',
            'value' => $otherCategory->id,
        ]);

    $response->assertSuccessful();

    expect($t1->fresh()->category_id)->toBe($otherCategory->id);
    expect($t2->fresh()->category_id)->toBe($otherCategory->id);
});

test('it bulk deletes transactions', function () {
    $t1 = Transaction::factory()->for($this->ledger)->for($this->account)->for($this->category)->expense()->create();
    $t2 = Transaction::factory()->for($this->ledger)->for($this->account)->for($this->category)->expense()->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/transactions/bulk-destroy", [
            'ids' => [$t1->id, $t2->id],
        ]);

    $response->assertNoContent();

    expect(Transaction::find($t1->id))->toBeNull();
    expect(Transaction::find($t2->id))->toBeNull();
});

test('it selects all matching filter', function () {
    Transaction::factory()->for($this->ledger)->for($this->account)->for($this->category)->expense()->create();
    Transaction::factory()->for($this->ledger)->for($this->account)->for($this->category)->expense()->create();

    $otherAccount = Account::factory()->for($this->ledger)->for($this->accountType)->create();
    Transaction::factory()->for($this->ledger)->for($otherAccount)->for($this->category)->expense()->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/transactions/select-all", [
            'account_ids' => [$this->account->id],
        ]);

    $response->assertSuccessful()
        ->assertJsonCount(2, 'ids');
});

test('it exports transactions as csv', function () {
    Transaction::factory()->for($this->ledger)->for($this->account)->for($this->category)->expense()->create([
        'description' => 'CSV test',
        'transaction_date' => '2026-03-15',
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->get("/api/v1/ledgers/{$this->ledger->id}/transactions/export?date_from=2026-03-01&date_to=2026-03-31");

    $response->assertSuccessful()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();
    expect($content)->toContain('Date,Description,Type,Account,Category,Payee,Amount,Notes')
        ->toContain('CSV test');
});
