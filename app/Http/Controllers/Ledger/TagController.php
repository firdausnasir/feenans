<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
use App\Models\Ledger;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function index(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/tags/index', [
            'tags' => Inertia::defer(function () use ($ledger): array {
                $tags = $ledger->tags()
                    ->withCount('transactions')
                    ->orderBy('name')
                    ->get();

                return TagResource::collection($tags)->resolve();
            }),
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

        return back()->with('success', 'Tag created.');
    }

    public function update(Request $request, Ledger $ledger, Tag $tag): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('tags', 'name')->where('ledger_id', $ledger->id)->ignore($tag->id),
            ],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $tag->update($validated);

        return back()->with('success', 'Tag updated.');
    }

    public function destroy(Ledger $ledger, Tag $tag): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $tag->delete();

        return back()->with('success', 'Tag deleted.');
    }
}
