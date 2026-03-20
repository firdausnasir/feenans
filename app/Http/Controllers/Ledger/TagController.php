<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\TagRequest;
use App\Http\Resources\TagResource;
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
            'tags' => Inertia::defer(function () use ($ledger) {
                return TagResource::collection(
                    $ledger->tags()
                        ->withCount('transactions')
                        ->orderBy('name')
                        ->get()
                )->resolve();
            }),
        ]);
    }

    public function store(TagRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('view', $ledger);

        $validated = $request->validated();

        $ledger->tags()->firstOrCreate(
            ['name' => $validated['name']],
            ['color' => $validated['color'] ?? null],
        );

        return to_route('ledgers.tags.index', $ledger)->with('success', 'Tag created.');
    }

    public function update(TagRequest $request, Ledger $ledger, Tag $tag): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $validated = $request->validated();

        $tag->update($validated);

        return to_route('ledgers.tags.index', $ledger)->with('success', 'Tag updated.');
    }

    public function destroy(Ledger $ledger, Tag $tag): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $tag->delete();

        return to_route('ledgers.tags.index', $ledger)->with('success', 'Tag deleted.');
    }
}
