<?php

use App\Models\Ledger;
use App\Models\User;

test('unauthenticated requests return 401', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->getJson("/api/v1/ledgers/{$ledger->id}/transactions")
        ->assertUnauthorized();

    $this->getJson("/api/v1/ledgers/{$ledger->id}/accounts")
        ->assertUnauthorized();

    $this->getJson("/api/v1/ledgers/{$ledger->id}/categories")
        ->assertUnauthorized();

    $this->getJson("/api/v1/ledgers/{$ledger->id}/payees")
        ->assertUnauthorized();

    $this->getJson("/api/v1/ledgers/{$ledger->id}/bills")
        ->assertUnauthorized();

    $this->getJson("/api/v1/ledgers/{$ledger->id}/tags")
        ->assertUnauthorized();
});

test('authenticated requests with valid token succeed', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $token = $user->createToken('test');

    $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$ledger->id}/accounts")
        ->assertSuccessful();
});

test('user cannot access another users ledger via api', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();
    $token = $other->createToken('test');

    $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$ledger->id}/transactions")
        ->assertForbidden();
});

test('invalid token returns 401', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->withHeader('Authorization', 'Bearer invalid-token-value')
        ->getJson("/api/v1/ledgers/{$ledger->id}/accounts")
        ->assertUnauthorized();
});
