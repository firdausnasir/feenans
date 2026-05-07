<?php

use App\Enums\ApiTokenAbility;
use App\Models\Ledger;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

test('authenticated web user can create a named first party token', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('api.v1.auth.tokens.store'), [
            'device_name' => 'MacBook Pro',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'MacBook Pro')
        ->assertJsonPath('data.abilities', ['*'])
        ->assertJsonPath('data.last_used_at', null)
        ->assertJsonPath('data.plain_text_token', fn (mixed $token): bool => is_string($token) && str_contains($token, '|'));

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_type' => $user->getMorphClass(),
        'tokenable_id' => $user->id,
        'name' => 'MacBook Pro',
    ]);
});

test('token creation returns plain token once plus token metadata', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('api.v1.auth.tokens.store'), [
            'device_name' => 'CLI',
        ]);

    $tokenId = $user->tokens()->firstOrFail()->id;

    $response->assertCreated()
        ->assertJsonPath('data.id', $tokenId)
        ->assertJsonPath('data.name', 'CLI')
        ->assertJsonPath('data.abilities', ['*'])
        ->assertJsonPath('data.plain_text_token', fn (mixed $token): bool => is_string($token) && str_contains($token, '|'))
        ->assertJsonPath('data.created_at', fn (mixed $createdAt): bool => is_string($createdAt) && $createdAt !== '')
        ->assertJsonPath('data.last_used_at', null)
        ->assertJsonMissingPath('data.token');
});

test('authenticated web user can create a ledger scoped webhook token', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->postJson(route('api.v1.auth.tokens.store'), [
            'device_name' => 'Webhook',
            'ledger_id' => $ledger->id,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Webhook')
        ->assertJsonPath('data.abilities', [ApiTokenAbility::transactionWebhookForLedger($ledger->id)])
        ->assertJsonPath('data.plain_text_token', fn (mixed $token): bool => is_string($token) && str_contains($token, '|'));
});

test('authenticated web user can still create a generic webhook token with explicit abilities', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('api.v1.auth.tokens.store'), [
            'device_name' => 'Webhook',
            'abilities' => [ApiTokenAbility::TransactionWebhook->value],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Webhook')
        ->assertJsonPath('data.abilities', [ApiTokenAbility::TransactionWebhook->value])
        ->assertJsonPath('data.plain_text_token', fn (mixed $token): bool => is_string($token) && str_contains($token, '|'));
});

test('authenticated web user can list token metadata without plain tokens', function () {
    $user = User::factory()->create();
    $user->createToken('Webhook', [ApiTokenAbility::TransactionWebhook->value]);
    $user->createToken('CLI');

    $response = $this->actingAs($user)
        ->getJson(route('api.v1.auth.tokens.index'));

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment([
            'name' => 'Webhook',
            'abilities' => [ApiTokenAbility::TransactionWebhook->value],
            'plain_text_token' => null,
        ])
        ->assertJsonFragment([
            'name' => 'CLI',
            'abilities' => ['*'],
            'plain_text_token' => null,
        ]);
});

test('authenticated web user can list only token metadata for a ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $otherLedger = Ledger::factory()->for($user)->create();
    $user->createToken('Ledger webhook', [ApiTokenAbility::transactionWebhookForLedger($ledger->id)]);
    $user->createToken('Other ledger webhook', [ApiTokenAbility::transactionWebhookForLedger($otherLedger->id)]);
    $user->createToken('Generic webhook', [ApiTokenAbility::TransactionWebhook->value]);

    $response = $this->actingAs($user)
        ->getJson(route('api.v1.auth.tokens.index', ['ledger_id' => $ledger->id]));

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Ledger webhook')
        ->assertJsonPath('data.0.abilities', [ApiTokenAbility::transactionWebhookForLedger($ledger->id)])
        ->assertJsonPath('data.0.plain_text_token', null);
});

test('bearer token requests cannot mint new tokens through the issuance endpoint', function () {
    $user = User::factory()->create();
    $existingToken = $user->createToken('Existing');

    $this->withToken($existingToken->plainTextToken)
        ->postJson(route('api.v1.auth.tokens.store'), [
            'device_name' => 'Should Not Work',
        ])
        ->assertUnauthorized();

    expect($user->tokens()->count())->toBe(1);
});

test('token can be revoked individually', function () {
    $user = User::factory()->create();
    $firstToken = $user->createToken('First');
    $secondToken = $user->createToken('Second');

    $secondTokenId = PersonalAccessToken::findToken($secondToken->plainTextToken)?->id;

    $this->actingAs($user)
        ->deleteJson(route('api.v1.auth.tokens.destroy', $secondTokenId))
        ->assertNoContent();

    expect(PersonalAccessToken::findToken($firstToken->plainTextToken))->not->toBeNull();
    expect(PersonalAccessToken::find($secondTokenId))->toBeNull();
});

test('current token can be revoked without an id', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Current');

    $this->withToken($token->plainTextToken)
        ->deleteJson(route('api.v1.auth.tokens.current.destroy'))
        ->assertNoContent();

    expect(PersonalAccessToken::findToken($token->plainTextToken))->toBeNull();
});

test('session authenticated requests cannot silently revoke a missing current token', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('api.v1.auth.tokens.current.destroy'))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'No current access token is available for this request.');
});

test('unauthenticated requests are rejected', function () {
    $this->postJson(route('api.v1.auth.tokens.store'), [
        'device_name' => 'MacBook Pro',
    ])->assertUnauthorized();

    $user = User::factory()->create();
    $token = $user->createToken('Current');
    $tokenId = PersonalAccessToken::findToken($token->plainTextToken)?->id;

    $this->deleteJson(route('api.v1.auth.tokens.destroy', $tokenId))
        ->assertUnauthorized();

    $this->deleteJson(route('api.v1.auth.tokens.current.destroy'))
        ->assertUnauthorized();
});
