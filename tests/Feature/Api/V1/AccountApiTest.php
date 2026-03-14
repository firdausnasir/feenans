<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Ledger;
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

test('account destroy soft deletes an account', function () {
    $account = Account::factory()->for($this->ledger)->for($this->accountType)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->deleteJson("/api/v1/ledgers/{$this->ledger->id}/accounts/{$account->id}");

    $response->assertNoContent();

    $this->assertSoftDeleted('accounts', ['id' => $account->id]);
});
