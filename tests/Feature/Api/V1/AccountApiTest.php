<?php

use App\Enums\TransactionType;
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
    $this->token = $this->user->createToken('test');
});

test('account index returns all ledger accounts', function () {
    Account::factory()->for($this->ledger)->for($this->accountType)->count(3)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/accounts");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'ledger_id',
                    'account_type_id',
                    'name',
                    'initial_balance',
                    'current_balance',
                    'include_in_totals',
                    'account_type',
                    'created_at',
                    'updated_at',
                ],
            ],
        ])
        ->assertJsonCount(3, 'data');
});

test('account show returns a single account', function () {
    $account = Account::factory()->for($this->ledger)->for($this->accountType)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/accounts/{$account->id}");

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $account->id)
        ->assertJsonPath('data.name', $account->name);
});

test('account store creates a new account', function () {
    $data = [
        'account_type_id' => $this->accountType->id,
        'name' => 'My Savings',
        'initial_balance' => 1000.50,
        'include_in_totals' => true,
    ];

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/accounts", $data);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'My Savings');

    $this->assertDatabaseHas('accounts', [
        'ledger_id' => $this->ledger->id,
        'name' => 'My Savings',
    ]);
});

test('account store validates required fields', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/accounts", []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['account_type_id', 'name', 'initial_balance', 'include_in_totals']);
});

test('account update modifies an existing account', function () {
    $account = Account::factory()->for($this->ledger)->for($this->accountType)->create(['name' => 'Old Name']);

    $data = [
        'account_type_id' => $this->accountType->id,
        'name' => 'New Name',
        'initial_balance' => 2000.00,
        'include_in_totals' => false,
    ];

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->putJson("/api/v1/ledgers/{$this->ledger->id}/accounts/{$account->id}", $data);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'New Name');
});

test('account destroy deletes an account', function () {
    $account = Account::factory()->for($this->ledger)->for($this->accountType)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->deleteJson("/api/v1/ledgers/{$this->ledger->id}/accounts/{$account->id}");

    $response->assertNoContent();

    expect(Account::find($account->id))->toBeNull();
});

test('it returns account with enhanced resource fields', function () {
    $account = Account::factory()->for($this->ledger)->for($this->accountType)->create([
        'color' => '#ff0000',
        'is_hidden' => false,
        'position' => 3,
        'payment_due_day' => 15,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/accounts/{$account->id}");

    $response->assertSuccessful()
        ->assertJsonPath('data.color', '#ff0000')
        ->assertJsonPath('data.is_hidden', false)
        ->assertJsonPath('data.position', 3)
        ->assertJsonPath('data.payment_due_day', 15);
});

test('it lists accounts grouped by type', function () {
    $bankType = AccountType::factory()->for($this->ledger)->create(['name' => 'Bank', 'position' => 1]);
    $creditType = AccountType::factory()->for($this->ledger)->credit()->create(['position' => 2]);

    Account::factory()->for($this->ledger)->for($bankType)->count(2)->create();
    Account::factory()->for($this->ledger)->for($creditType)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/accounts?grouped=1&with_type_totals=1");

    $response->assertSuccessful();

    $data = $response->json('data');
    expect($data)->toBeArray();

    $bankGroup = collect($data)->firstWhere('type.name', 'Bank');
    expect($bankGroup)->not->toBeNull();
    expect($bankGroup['accounts'])->toHaveCount(2);
    expect($bankGroup)->toHaveKey('total_balance');

    $creditGroup = collect($data)->firstWhere('type.name', 'Credit Card');
    expect($creditGroup)->not->toBeNull();
    expect($creditGroup['accounts'])->toHaveCount(1);
});

test('it excludes hidden accounts by default', function () {
    Account::factory()->for($this->ledger)->for($this->accountType)->count(2)->create();
    Account::factory()->for($this->ledger)->for($this->accountType)->hidden()->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/accounts");

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('it includes hidden accounts when show hidden is true', function () {
    Account::factory()->for($this->ledger)->for($this->accountType)->count(2)->create();
    Account::factory()->for($this->ledger)->for($this->accountType)->hidden()->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/accounts?show_hidden=1");

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('it lists account transactions paginated', function () {
    $account = Account::factory()->for($this->ledger)->for($this->accountType)->create();
    $category = Category::factory()->for($this->ledger)->create();
    $payee = Payee::factory()->for($this->ledger)->create();

    Transaction::factory()
        ->for($this->ledger)
        ->for($account)
        ->for($category)
        ->for($payee)
        ->expense()
        ->count(25)
        ->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/accounts/{$account->id}/transactions?per_page=10");

    $response->assertSuccessful();

    $data = $response->json('data');
    expect($data)->toHaveCount(10);

    $meta = $response->json('meta');
    expect($meta['total'])->toBe(25);
});

test('it returns statement info for credit account', function () {
    $creditType = AccountType::factory()->for($this->ledger)->credit()->create();
    $account = Account::factory()->for($this->ledger)->for($creditType)->create([
        'statement_day' => 15,
        'payment_due_day' => 5,
    ]);

    Transaction::factory()
        ->for($this->ledger)
        ->for($account)
        ->expense()
        ->create([
            'transaction_date' => now()->subDays(5)->toDateString(),
            'amount' => -100.00,
        ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/accounts/{$account->id}/statement");

    $response->assertSuccessful();

    $data = $response->json('data');
    expect($data['statement_start'])->not->toBeNull();
    expect($data['statement_end'])->not->toBeNull();
    expect($data['current_start'])->not->toBeNull();
    expect($data['current_end'])->not->toBeNull();
    expect($data['payment_due_date'])->not->toBeNull();
    expect($data)->toHaveKeys([
        'statement_balance',
        'current_spending',
        'outstanding',
    ]);
});

test('it returns null statement for non credit account', function () {
    $account = Account::factory()->for($this->ledger)->for($this->accountType)->create([
        'statement_day' => null,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/accounts/{$account->id}/statement");

    $response->assertSuccessful();

    $data = $response->json('data');
    expect($data['statement_start'])->toBeNull();
    expect($data['statement_end'])->toBeNull();
    expect($data['statement_balance'])->toBeNull();
    expect($data['current_start'])->toBeNull();
    expect($data['current_end'])->toBeNull();
    expect($data['current_spending'])->toBeNull();
    expect($data['outstanding'])->toBeNull();
    expect($data['payment_due_date'])->toBeNull();
});

test('it returns monthly balance trend', function () {
    $account = Account::factory()->for($this->ledger)->for($this->accountType)->create([
        'initial_balance' => 1000.00,
    ]);

    Transaction::factory()
        ->for($this->ledger)
        ->for($account)
        ->income()
        ->create([
            'amount' => 500.00,
            'transaction_date' => now()->subMonth()->toDateString(),
        ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/accounts/{$account->id}/monthly-balances?months=3");

    $response->assertSuccessful();

    $data = $response->json('data');
    expect($data)->toHaveCount(3);
    expect($data[0])->toHaveKeys(['month', 'balance']);
});

test('it returns net worth with trend', function () {
    $bankType = AccountType::factory()->for($this->ledger)->create(['is_credit' => false]);
    $creditType = AccountType::factory()->for($this->ledger)->credit()->create();

    Account::factory()->for($this->ledger)->for($bankType)->create(['initial_balance' => 5000.00]);
    Account::factory()->for($this->ledger)->for($creditType)->create(['initial_balance' => -1000.00]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/net-worth");

    $response->assertSuccessful();

    $data = $response->json('data');
    expect($data)->toHaveKeys(['assets', 'liabilities', 'net', 'trend']);
    expect((float) $data['assets'])->toBe(5000.0);
    expect((float) $data['liabilities'])->toBe(-1000.0);
    expect((float) $data['net'])->toBe(4000.0);
    expect($data['trend'])->toBeArray();
    expect($data['trend'])->toHaveCount(6);
    expect($data['trend'][0])->toHaveKeys(['month', 'net']);
});

test('it toggles account visibility', function () {
    $account = Account::factory()->for($this->ledger)->for($this->accountType)->create(['is_hidden' => false]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->patchJson("/api/v1/ledgers/{$this->ledger->id}/accounts/{$account->id}/toggle-visibility");

    $response->assertSuccessful();
    expect($account->fresh()->is_hidden)->toBeTrue();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->patchJson("/api/v1/ledgers/{$this->ledger->id}/accounts/{$account->id}/toggle-visibility");

    $response->assertSuccessful();
    expect($account->fresh()->is_hidden)->toBeFalse();
});

test('it adjusts account balance', function () {
    $account = Account::factory()->for($this->ledger)->for($this->accountType)->create([
        'initial_balance' => 1000.00,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/accounts/{$account->id}/adjust-balance", [
            'amount' => 250.50,
            'date' => now()->toDateString(),
            'description' => 'Test adjustment',
        ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('transactions', [
        'account_id' => $account->id,
        'amount' => '250.50',
        'description' => 'Test adjustment',
        'transaction_type' => TransactionType::Income->value,
    ]);
});

test('it reorders accounts', function () {
    $account1 = Account::factory()->for($this->ledger)->for($this->accountType)->create(['position' => 0]);
    $account2 = Account::factory()->for($this->ledger)->for($this->accountType)->create(['position' => 1]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/accounts/reorder", [
            'items' => [
                ['id' => $account1->id, 'position' => 1],
                ['id' => $account2->id, 'position' => 0],
            ],
        ]);

    $response->assertNoContent();

    expect($account1->fresh()->position)->toBe(1);
    expect($account2->fresh()->position)->toBe(0);
});

test('it exports account transactions as csv', function () {
    $account = Account::factory()->for($this->ledger)->for($this->accountType)->create();
    $category = Category::factory()->for($this->ledger)->create();
    $payee = Payee::factory()->for($this->ledger)->create();

    Transaction::factory()
        ->for($this->ledger)
        ->for($account)
        ->for($category)
        ->for($payee)
        ->expense()
        ->count(3)
        ->create([
            'transaction_date' => now()->toDateString(),
        ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->get("/api/v1/ledgers/{$this->ledger->id}/accounts/{$account->id}/export?format=csv");

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});
