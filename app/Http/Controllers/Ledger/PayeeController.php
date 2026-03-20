<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePayeeRequest;
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

    public function store(UpdatePayeeRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('view', $ledger);

        $ledger->payees()->create($request->validated());

        return back()->with('success', 'Payee added.');
    }

    public function update(UpdatePayeeRequest $request, Ledger $ledger, Payee $payee): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $payee->update($request->validated());

        return back()->with('success', 'Payee updated.');
    }

    public function destroy(Ledger $ledger, Payee $payee): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $payee->delete();

        return back()->with('success', 'Payee deleted.');
    }
}
