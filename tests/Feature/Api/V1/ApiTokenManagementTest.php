<?php

use App\Models\User;

test('user can create api token via api', function () {
    $user = User::factory()->create();
    $token = $user->createToken('bootstrap');

    $response = $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
        ->postJson('/api/v1/tokens', ['name' => 'My App Token']);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => ['id', 'name', 'abilities', 'plain_text_token', 'created_at'],
        ])
        ->assertJsonPath('data.name', 'My App Token');
});

test('user can list api tokens via api', function () {
    $user = User::factory()->create();
    $user->createToken('Token One');
    $user->createToken('Token Two');
    $token = $user->createToken('Token Three');

    $response = $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
        ->getJson('/api/v1/tokens');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('user can revoke api token via api', function () {
    $user = User::factory()->create();
    $tokenToRevoke = $user->createToken('Revocable');
    $activeToken = $user->createToken('Active');

    $response = $this->withHeader('Authorization', "Bearer {$activeToken->plainTextToken}")
        ->deleteJson("/api/v1/tokens/{$tokenToRevoke->accessToken->id}");

    $response->assertNoContent();

    expect($user->tokens()->count())->toBe(1);
});

test('user can create api token via web', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post('/api-tokens', ['name' => 'Web Token']);

    $response->assertRedirect()
        ->assertSessionHas('newToken');
});

test('user can revoke api token via web', function () {
    $user = User::factory()->create();
    $token = $user->createToken('To Delete');

    $response = $this->actingAs($user)
        ->delete("/api-tokens/{$token->accessToken->id}");

    $response->assertRedirect();

    expect($user->tokens()->count())->toBe(0);
});

test('token creation requires a name', function () {
    $user = User::factory()->create();
    $token = $user->createToken('bootstrap');

    $response = $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
        ->postJson('/api/v1/tokens', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});
