<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function index(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/tags/index', [
            'ledger' => $ledger,
            'tags' => $ledger->tags()
                ->withCount('transactions')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('view', $ledger);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $ledger->tags()->firstOrCreate(
            ['name' => $validated['name']],
            ['color' => $validated['color'] ?? null]
        );

        return back();
    }

    public function update(Request $request, Ledger $ledger, Tag $tag): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $tag->update($validated);

        return back();
    }

    public function destroy(Request $request, Ledger $ledger, Tag $tag): RedirectResponse
    {
        $this->authorize('delete', $ledger);
        $tag->delete();

        return back();
    }
}
