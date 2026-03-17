<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Ledger;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->ledger = Ledger::factory()->for($this->user)->create();
    $this->token = $this->user->createToken('test');
});

test('it lists account types', function () {
    AccountType::factory()->for($this->ledger)->count(3)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/account-types");

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('it creates an account type', function () {
    $data = [
        'name' => 'Savings',
        'color' => '#00ff00',
        'is_credit' => false,
    ];

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/account-types", $data);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Savings')
        ->assertJsonPath('data.is_credit', false);

    $this->assertDatabaseHas('account_types', [
        'ledger_id' => $this->ledger->id,
        'name' => 'Savings',
    ]);
});

test('it shows a single account type', function () {
    $accountType = AccountType::factory()->for($this->ledger)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/account-types/{$accountType->id}");

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $accountType->id);
});

test('it updates an account type', function () {
    $accountType = AccountType::factory()->for($this->ledger)->create(['name' => 'Old']);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->putJson("/api/v1/ledgers/{$this->ledger->id}/account-types/{$accountType->id}", [
            'name' => 'Updated',
            'color' => '#ff0000',
            'is_credit' => true,
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Updated')
        ->assertJsonPath('data.is_credit', true);
});

test('it deletes an account type without accounts', function () {
    $accountType = AccountType::factory()->for($this->ledger)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->deleteJson("/api/v1/ledgers/{$this->ledger->id}/account-types/{$accountType->id}");

    $response->assertNoContent();

    expect(AccountType::find($accountType->id))->toBeNull();
});

test('it cannot delete an account type with accounts', function () {
    $accountType = AccountType::factory()->for($this->ledger)->create();
    Account::factory()->for($this->ledger)->for($accountType)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->deleteJson("/api/v1/ledgers/{$this->ledger->id}/account-types/{$accountType->id}");

    $response->assertUnprocessable();

    expect(AccountType::find($accountType->id))->not->toBeNull();
});

test('it reorders account types', function () {
    $type1 = AccountType::factory()->for($this->ledger)->create(['position' => 0]);
    $type2 = AccountType::factory()->for($this->ledger)->create(['position' => 1]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/account-types/reorder", [
            'items' => [
                ['id' => $type1->id, 'position' => 1],
                ['id' => $type2->id, 'position' => 0],
            ],
        ]);

    $response->assertNoContent();

    expect($type1->fresh()->position)->toBe(1);
    expect($type2->fresh()->position)->toBe(0);
});

test('it validates required fields when creating account type', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/account-types", []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'is_credit']);
});

test('it assigns position automatically when creating', function () {
    AccountType::factory()->for($this->ledger)->count(2)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/account-types", [
            'name' => 'New Type',
            'is_credit' => false,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.position', 3);
});
