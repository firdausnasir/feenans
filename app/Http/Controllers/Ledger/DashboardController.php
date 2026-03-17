<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Ledger $ledger, Request $request): Response
    {
        $this->authorize('view', $ledger);
        $request->session()->put('current_ledger_id', $ledger->id);

        return Inertia::render('ledgers/dashboard');
    }
}
