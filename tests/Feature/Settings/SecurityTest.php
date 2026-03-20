<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

test('security page is displayed', function () {
    $this->skipUnlessFortifyFeature(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/security')
            ->where('canManageTwoFactor', true)
            ->where('twoFactorEnabled', false)
            ->has('passwordReset', fn (Assert $passwordReset) => $passwordReset
                ->where('email', $user->email)
                ->where('status', null)
                ->etc()
            )
            ->missing('twoFactorQrCodeSvg')
            ->missing('twoFactorSecretKey')
            ->missing('twoFactorRecoveryCodes')
        );
});

test('security page loads two factor setup props on reload only when enabled', function () {
    $this->skipUnlessFortifyFeature(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => false,
    ]);

    $user = User::factory()->create([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code-1', 'code-2'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/security')
            ->where('twoFactorEnabled', true)
            ->missing('twoFactorQrCodeSvg')
            ->missing('twoFactorSecretKey')
            ->missing('twoFactorRecoveryCodes')
            ->reloadOnly('twoFactorQrCodeSvg', fn (Assert $reload) => $reload
                ->has('twoFactorQrCodeSvg')
                ->missing('twoFactorSecretKey')
                ->missing('twoFactorRecoveryCodes')
            )
            ->reloadOnly('twoFactorSecretKey', fn (Assert $reload) => $reload
                ->where('twoFactorSecretKey', 'test-secret')
                ->missing('twoFactorQrCodeSvg')
                ->missing('twoFactorRecoveryCodes')
            )
            ->reloadOnly('twoFactorRecoveryCodes', fn (Assert $reload) => $reload
                ->where('twoFactorRecoveryCodes', ['code-1', 'code-2'])
                ->missing('twoFactorQrCodeSvg')
                ->missing('twoFactorSecretKey')
            )
        );
});

test('security page requires password confirmation when enabled', function () {
    $this->skipUnlessFortifyFeature(Features::twoFactorAuthentication());

    $user = User::factory()->create();

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $response = $this->actingAs($user)
        ->get(route('security.edit'));

    $response->assertRedirect(route('password.confirm'));
});

test('security page does not require password confirmation when disabled', function () {
    $this->skipUnlessFortifyFeature(Features::twoFactorAuthentication());

    $user = User::factory()->create();

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => false,
    ]);

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/security'),
        );
});

test('security page renders without two factor when feature is disabled', function () {
    $this->skipUnlessFortifyFeature(Features::twoFactorAuthentication());

    config(['fortify.features' => []]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/security')
            ->where('canManageTwoFactor', false)
            ->missing('twoFactorEnabled')
            ->missing('requiresConfirmation'),
        );
});

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('security page can send password reset link for current user', function () {
    $this->skipUnlessFortifyFeature(Features::resetPasswords());

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->post(route('security.password-reset-link'));

    $response->assertRedirect(route('security.edit'));

    $this->actingAs($user)
        ->withSession([
            'auth.password_confirmed_at' => time(),
            'status' => trans('passwords.sent'),
        ])
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('passwordReset.email', $user->email)
            ->where('passwordReset.status', trans('passwords.sent'))
        );
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('security.edit'));
});
