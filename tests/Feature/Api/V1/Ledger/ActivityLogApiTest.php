<?php

use App\Models\ActivityLog;
use App\Models\Budget;
use App\Models\Ledger;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('activity api returns paginated activity data for the current ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    ActivityLog::query()->create([
        'ledger_id' => $ledger->id,
        'user_id' => $user->id,
        'action' => 'created',
        'subject_type' => Budget::class,
        'subject_id' => 101,
        'old_values' => [],
        'new_values' => ['name' => 'Groceries'],
        'created_at' => now()->subMinute(),
    ]);

    ActivityLog::query()->create([
        'ledger_id' => $ledger->id,
        'user_id' => $user->id,
        'action' => 'updated',
        'subject_type' => Budget::class,
        'subject_id' => 102,
        'old_values' => ['name' => 'Food'],
        'new_values' => ['name' => 'Dining'],
        'created_at' => now(),
    ]);

    Sanctum::actingAs($user, ['*']);

    $this->getJson(route('api.v1.ledgers.activity.index', [
        'ledger' => $ledger,
        'subject_type' => 'Budget',
        'action' => 'updated',
        'page' => 1,
    ]))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.subject_type', 'Budget')
        ->assertJsonPath('data.0.action', 'updated')
        ->assertJsonPath('data.0.subject_id', 102)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.per_page', 50);
});

test('activity api is forbidden for another users ledger', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();

    Sanctum::actingAs($other, ['*']);

    $this->getJson(route('api.v1.ledgers.activity.index', $ledger))
        ->assertForbidden();
});
