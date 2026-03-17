<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class HomeController extends Controller
{
    /**
     * Show the welcome page or redirect authenticated users.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return Inertia::render('welcome', [
                'canRegister' => Features::enabled(Features::registration()),
            ]);
        }

        $ledger = $user->ledgers()->orderBy('id')->first();

        if ($ledger === null) {
            return to_route('onboarding.show');
        }

        return to_route('ledgers.dashboard', $ledger);
    }
}
