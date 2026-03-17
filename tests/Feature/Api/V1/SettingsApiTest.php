<?php

use App\Models\AccountType;
use App\Models\Ledger;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->ledger = Ledger::factory()->for($this->user)->create();
    $this->token = $this->user->createToken('test');
});

test('it returns ledger settings with account types', function () {
    AccountType::factory()->for($this->ledger)->create(['name' => 'Bank', 'position' => 1]);
    AccountType::factory()->for($this->ledger)->create(['name' => 'Cash', 'position' => 2]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/settings");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'ledger' => ['id', 'name', 'currency_code'],
            'account_types',
            'has_sample_data',
            'api_tokens',
        ])
        ->assertJsonPath('ledger.id', $this->ledger->id)
        ->assertJsonPath('has_sample_data', false)
        ->assertJsonCount(2, 'account_types');
});

test('it updates ledger settings', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->putJson("/api/v1/ledgers/{$this->ledger->id}/settings", [
            'name' => 'Updated Ledger',
            'cycle_start_day' => 15,
            'currency_code' => 'USD',
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Updated Ledger')
        ->assertJsonPath('data.cycle_start_day', 15)
        ->assertJsonPath('data.currency_code', 'USD');

    $this->assertDatabaseHas('ledgers', [
        'id' => $this->ledger->id,
        'name' => 'Updated Ledger',
        'cycle_start_day' => 15,
    ]);
});

test('it generates sample data', function () {
    AccountType::factory()->for($this->ledger)->create(['name' => 'Bank']);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/sample-data");

    $response->assertCreated()
        ->assertJsonPath('message', 'Sample data generated successfully.');

    // Verify sample data exists
    expect($this->ledger->accounts()->where('is_sample', true)->exists())->toBeTrue();
});

test('it returns 409 when sample data already exists', function () {
    AccountType::factory()->for($this->ledger)->create(['name' => 'Bank']);

    // Generate sample data first
    $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/sample-data");

    // Try again
    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/sample-data");

    $response->assertStatus(409)
        ->assertJsonPath('message', 'Sample data already exists.');
});

test('it removes sample data', function () {
    AccountType::factory()->for($this->ledger)->create(['name' => 'Bank']);

    // Generate first
    $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/sample-data");

    // Remove
    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->deleteJson("/api/v1/ledgers/{$this->ledger->id}/sample-data");

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Sample data removed successfully.');

    expect($this->ledger->accounts()->where('is_sample', true)->exists())->toBeFalse();
});

test('it returns 401 when unauthenticated for settings', function () {
    $response = $this->getJson("/api/v1/ledgers/{$this->ledger->id}/settings");

    $response->assertUnauthorized();
});
