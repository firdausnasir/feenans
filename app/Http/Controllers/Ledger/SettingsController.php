<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Ledger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/settings/index', [
            'ledger' => $ledger->load(['accountTypes' => fn ($q) => $q->orderBy('position')]),
        ]);
    }

    public function update(UpdateSettingsRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $ledger->update($request->validated());

        return back()->with('success', 'Settings updated successfully.');
    }
}
