<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class SecurityController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return Features::canManageTwoFactorAuthentication()
            && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')
                ? [new Middleware('password.confirm', only: ['edit'])]
                : [];
    }

    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        $props = [
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $request->ensureStateIsValid();

            $props['twoFactorEnabled'] = $request->user()->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
            $props['twoFactorQrCodeSvg'] = Inertia::optional(function () use ($request): ?string {
                if ($request->user()->two_factor_secret === null) {
                    return null;
                }

                return $request->user()->twoFactorQrCodeSvg();
            });
            $props['twoFactorSecretKey'] = Inertia::optional(function () use ($request): ?string {
                if ($request->user()->two_factor_secret === null) {
                    return null;
                }

                return decrypt($request->user()->two_factor_secret);
            });
            $props['twoFactorRecoveryCodes'] = Inertia::optional(function () use ($request): array {
                if ($request->user()->two_factor_secret === null || $request->user()->two_factor_recovery_codes === null) {
                    return [];
                }

                return $request->user()->recoveryCodes();
            });
        }

        if (Features::enabled(Features::resetPasswords())) {
            $props['passwordReset'] = [
                'email' => $request->user()->email,
                'status' => session('status'),
            ];
        }

        return Inertia::render('settings/security', $props);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        return back();
    }

    public function sendResetLink(TwoFactorAuthenticationRequest $request): RedirectResponse
    {
        $status = Password::sendResetLink([
            'email' => $request->user()->email,
        ]);

        return to_route('security.edit')->with('status', __($status));
    }
}
