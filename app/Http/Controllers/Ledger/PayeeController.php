<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Models\Payee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayeeController extends Controller
{
    public function index(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        $search = $request->input('search');

        return Inertia::render('ledgers/payees/index', [
            'search' => $search ?? '',
            'payees' => Inertia::defer(function () use ($ledger, $search) {
                $query = $ledger->payees()
                    ->withCount('transactions')
                    ->orderBy('name');

                if ($search) {
                    $query->where('name', 'like', "%{$search}%");
                }

                return $query->get();
            }),
        ]);
    }

    public function store(Request $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('view', $ledger);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $ledger->payees()->create($validated);

        return back()->with('success', 'Payee added.');
    }

    public function update(Request $request, Ledger $ledger, Payee $payee): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $payee->update($validated);

        return back()->with('success', 'Payee updated.');
    }

    public function destroy(Ledger $ledger, Payee $payee): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $payee->delete();

        return back()->with('success', 'Payee deleted.');
    }
}
