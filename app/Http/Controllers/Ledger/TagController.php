<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
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

    public function destroy(Request $request, Ledger $ledger, Tag $tag): RedirectResponse
    {
        $this->authorize('delete', $ledger);
        $tag->delete();

        return back();
    }
}
