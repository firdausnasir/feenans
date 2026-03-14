<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLedgerRequest;
use App\Http\Requests\UpdateLedgerRequest;
use App\Models\Ledger;
use App\Services\LedgerSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LedgerController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('ledgers/index', [
            'ledgers' => $request->user()->ledgers()->orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('ledgers/create', [
            'defaults' => [
                'currency_code' => 'MYR',
                'uses_seeded_categories' => true,
            ],
        ]);
    }

    public function store(StoreLedgerRequest $request, LedgerSetupService $ledgerSetupService): RedirectResponse
    {
        $ledger = $ledgerSetupService->createForUser($request->user(), $request->validated());

        return to_route('ledgers.dashboard', $ledger);
    }

    public function edit(Ledger $ledger): Response
    {
        $this->authorize('update', $ledger);

        return Inertia::render('ledgers/edit', [
            'ledger' => $ledger,
        ]);
    }

    public function update(UpdateLedgerRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $ledger->update($request->validated());

        return to_route('ledgers.edit', $ledger);
    }

    public function destroy(Request $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $ledger->delete();

        return to_route('ledgers.index');
    }
}
