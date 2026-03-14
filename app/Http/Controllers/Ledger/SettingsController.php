<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Ledger;
use App\Services\SampleDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request, Ledger $ledger, SampleDataService $sampleDataService): Response
    {
        $this->authorize('view', $ledger);

        $apiTokens = $request->user()->tokens()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
            ]);

        return Inertia::render('ledgers/settings/index', [
            'ledger' => $ledger->load(['accountTypes' => fn ($q) => $q->orderBy('position')]),
            'hasSampleData' => $sampleDataService->hasSampleData($ledger),
            'apiTokens' => $apiTokens,
            'newToken' => session('newToken'),
        ]);
    }

    public function update(UpdateSettingsRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $ledger->update($request->validated());

        return back()->with('success', 'Settings updated successfully.');
    }
}
