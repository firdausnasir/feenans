<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PrivacyModeController extends Controller
{
    /**
     * Toggle the authenticated user's privacy mode.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->update(['privacy_mode' => ! $user->privacy_mode]);

        return back();
    }
}
