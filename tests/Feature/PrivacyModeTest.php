<?php

use App\Models\User;

test('authenticated user can toggle privacy mode on', function () {
    $user = User::factory()->create(['privacy_mode' => false]);

    $this->actingAs($user)
        ->patch(route('user.privacy-mode.toggle'))
        ->assertRedirect();

    expect($user->fresh()->privacy_mode)->toBeTrue();
});

test('authenticated user can toggle privacy mode off', function () {
    $user = User::factory()->create(['privacy_mode' => false]);

    $this->actingAs($user)
        ->patch(route('user.privacy-mode.toggle'));

    $this->actingAs($user)
        ->patch(route('user.privacy-mode.toggle'))
        ->assertRedirect();

    expect($user->fresh()->privacy_mode)->toBeFalse();
});

test('unauthenticated user gets redirected', function () {
    $this->patch(route('user.privacy-mode.toggle'))
        ->assertRedirect();
});
