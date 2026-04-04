<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
     * Render the security settings shell.
     *
     * Security config flags (canManageTwoFactor, twoFactorEnabled, etc.) are
     * loaded client-side via the API. Only session-dependent data that cannot
     * survive a separate HTTP request is passed as an Inertia prop.
     */
    public function edit(Request $request): Response
    {
        $props = [];

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
