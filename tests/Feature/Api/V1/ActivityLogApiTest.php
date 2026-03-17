<?php

use App\Models\ActivityLog;
use App\Models\Ledger;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->ledger = Ledger::factory()->for($this->user)->create();
    $this->token = $this->user->createToken('test');
});

test('it lists activity logs paginated', function () {
    // Create activity log entries
    foreach (range(1, 5) as $i) {
        ActivityLog::query()->create([
            'user_id' => $this->user->id,
            'ledger_id' => $this->ledger->id,
            'subject_type' => 'App\\Models\\Transaction',
            'subject_id' => $i,
            'action' => 'created',
            'old_values' => [],
            'new_values' => ['amount' => 100 * $i],
            'created_at' => now()->subMinutes($i),
        ]);
    }

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/activity?per_page=3");

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [['id', 'action', 'subject_type', 'subject_id', 'old_values', 'new_values', 'user', 'created_at']],
            'current_page',
            'last_page',
            'per_page',
            'total',
        ])
        ->assertJsonPath('total', 5);
});

test('it filters activity by subject type', function () {
    ActivityLog::query()->create([
        'user_id' => $this->user->id,
        'ledger_id' => $this->ledger->id,
        'subject_type' => 'App\\Models\\Transaction',
        'subject_id' => 1,
        'action' => 'created',
        'old_values' => [],
        'new_values' => [],
        'created_at' => now(),
    ]);

    ActivityLog::query()->create([
        'user_id' => $this->user->id,
        'ledger_id' => $this->ledger->id,
        'subject_type' => 'App\\Models\\Account',
        'subject_id' => 1,
        'action' => 'created',
        'old_values' => [],
        'new_values' => [],
        'created_at' => now(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/activity?subject_type=Transaction");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.subject_type', 'Transaction');
});

test('it filters activity by action', function () {
    ActivityLog::query()->create([
        'user_id' => $this->user->id,
        'ledger_id' => $this->ledger->id,
        'subject_type' => 'App\\Models\\Transaction',
        'subject_id' => 1,
        'action' => 'created',
        'old_values' => [],
        'new_values' => [],
        'created_at' => now(),
    ]);

    ActivityLog::query()->create([
        'user_id' => $this->user->id,
        'ledger_id' => $this->ledger->id,
        'subject_type' => 'App\\Models\\Transaction',
        'subject_id' => 2,
        'action' => 'updated',
        'old_values' => ['amount' => 100],
        'new_values' => ['amount' => 200],
        'created_at' => now(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/activity?action=updated");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.action', 'updated');
});

test('it returns 401 when unauthenticated for activity', function () {
    $response = $this->getJson("/api/v1/ledgers/{$this->ledger->id}/activity");

    $response->assertUnauthorized();
});
