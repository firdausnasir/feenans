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
    $this->token = $this->user->createToken('test');
});

test('it lists payees with transaction counts', function () {
    $payee = Payee::factory()->for($this->ledger)->create();
    $account = Account::factory()->for($this->ledger)->for($this->accountType)->create();
    $category = Category::factory()->for($this->ledger)->create();
    Transaction::factory()->for($this->ledger)->for($account)->for($category)->for($payee)->count(2)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/payees?with_counts=1");

    $response->assertSuccessful()
        ->assertJsonPath('data.0.transactions_count', 2);
});

test('it searches payees by name', function () {
    Payee::factory()->for($this->ledger)->create(['name' => 'Walmart']);
    Payee::factory()->for($this->ledger)->create(['name' => 'Target']);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/payees?search=Wal");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Walmart');
});

test('it creates a payee with validation', function () {
    $data = ['name' => 'New Payee'];

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/payees", $data);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'New Payee');

    $this->assertDatabaseHas('payees', [
        'ledger_id' => $this->ledger->id,
        'name' => 'New Payee',
    ]);
});

test('it returns 422 for invalid payee data', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/payees", []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('it updates a payee', function () {
    $payee = Payee::factory()->for($this->ledger)->create(['name' => 'Old Name']);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->putJson("/api/v1/ledgers/{$this->ledger->id}/payees/{$payee->id}", [
            'name' => 'New Name',
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'New Name');
});

test('it deletes a payee', function () {
    $payee = Payee::factory()->for($this->ledger)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->deleteJson("/api/v1/ledgers/{$this->ledger->id}/payees/{$payee->id}");

    $response->assertNoContent();

    expect(Payee::find($payee->id))->toBeNull();
});

test('it merges payees and reassigns transactions', function () {
    $sourcePayee = Payee::factory()->for($this->ledger)->create();
    $targetPayee = Payee::factory()->for($this->ledger)->create();
    $account = Account::factory()->for($this->ledger)->for($this->accountType)->create();
    $category = Category::factory()->for($this->ledger)->create();
    $transaction = Transaction::factory()->for($this->ledger)->for($account)->for($category)->for($sourcePayee)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/payees/merge", [
            'source_id' => $sourcePayee->id,
            'target_id' => $targetPayee->id,
        ]);

    $response->assertSuccessful();

    expect(Transaction::find($transaction->id)->payee_id)->toBe($targetPayee->id);
    expect(Payee::find($sourcePayee->id))->toBeNull();
});

test('it returns 401 when unauthenticated for payees', function () {
    $response = $this->getJson("/api/v1/ledgers/{$this->ledger->id}/payees");

    $response->assertUnauthorized();
});
