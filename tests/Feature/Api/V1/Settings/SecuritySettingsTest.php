<?php

use App\Models\User;
use Laravel\Fortify\Features;

test('security settings endpoint returns config flags when 2fa is available', function () {
    $this->skipUnlessFortifyFeature(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => false,
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.v1.settings.security'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'canManageTwoFactor',
                'requiresConfirmation',
                'twoFactorEnabled',
            ],
        ])
        ->assertJson([
            'data' => [
                'canManageTwoFactor' => true,
                'requiresConfirmation' => true,
                'twoFactorEnabled' => false,
            ],
        ]);
});

test('security settings endpoint reflects enabled 2fa status', function () {
    $this->skipUnlessFortifyFeature(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => false,
        'confirmPassword' => false,
    ]);

    $user = User::factory()->create([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code-1', 'code-2'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $this->actingAs($user)
        ->getJson(route('api.v1.settings.security'))
        ->assertOk()
        ->assertJson([
            'data' => [
                'canManageTwoFactor' => true,
                'requiresConfirmation' => false,
                'twoFactorEnabled' => true,
            ],
        ]);
});

test('security settings endpoint returns false for 2fa when feature is disabled', function () {
    $this->skipUnlessFortifyFeature(Features::twoFactorAuthentication());

    config(['fortify.features' => []]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.v1.settings.security'))
        ->assertOk()
        ->assertJson([
            'data' => [
                'canManageTwoFactor' => false,
                'requiresConfirmation' => false,
                'twoFactorEnabled' => false,
            ],
        ]);
});

test('security settings endpoint requires authentication', function () {
    $this->getJson(route('api.v1.settings.security'))
        ->assertUnauthorized();
});
