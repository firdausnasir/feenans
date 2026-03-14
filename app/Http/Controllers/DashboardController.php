<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Redirect the user into their active ledger context.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        $currentLedgerId = $request->session()->get('current_ledger_id');

        $ledger = $user->ledgers()
            ->when($currentLedgerId, fn ($query) => $query->whereKey($currentLedgerId))
            ->first();

        if ($ledger === null) {
            $ledger = $user->ledgers()->orderBy('name')->first();
        }

        if ($ledger === null) {
            return to_route('ledgers.create');
        }

        $request->session()->put('current_ledger_id', $ledger->id);

        return to_route('ledgers.dashboard', $ledger);
    }
}
