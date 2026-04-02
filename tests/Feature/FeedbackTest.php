<?php

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows authenticated user to submit feedback', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/feedback', [
        'type' => 'general',
        'message' => 'Great app!',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('feedbacks', [
        'user_id' => $user->id,
        'type' => 'general',
        'message' => 'Great app!',
    ]);
});

it('validates feedback type must be valid', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/feedback', [
        'type' => 'invalid',
        'message' => 'Test',
    ]);

    $response->assertSessionHasErrors('type');
});

it('validates feedback message is required', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/feedback', [
        'type' => 'general',
        'message' => '',
    ]);

    $response->assertSessionHasErrors('message');
});

it('validates feedback message max length', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/feedback', [
        'type' => 'bug',
        'message' => str_repeat('a', 2001),
    ]);

    $response->assertSessionHasErrors('message');
});

it('requires authentication to submit feedback', function () {
    $response = $this->post('/feedback', [
        'type' => 'general',
        'message' => 'Test',
    ]);

    $response->assertRedirect('/login');
});

it('allows admin to view feedbacks via API', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create();

    Feedback::create([
        'user_id' => $user->id,
        'type' => 'bug',
        'message' => 'Found a bug',
    ]);

    $response = $this->actingAs($admin)->getJson('/api/admin/feedbacks');

    $response->assertOk();
    $response->assertJsonPath('data.0.message', 'Found a bug');
    $response->assertJsonPath('data.0.user.name', $user->name);
});

it('prevents non-admin from viewing feedbacks API', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $response = $this->actingAs($user)->getJson('/api/admin/feedbacks');

    $response->assertForbidden();
});
