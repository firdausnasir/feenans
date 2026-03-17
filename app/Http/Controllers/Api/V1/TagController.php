<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
use App\Models\Ledger;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagController extends Controller
{
    public function index(Request $request, Ledger $ledger): AnonymousResourceCollection
    {
        $this->authorize('view', $ledger);

        $query = $ledger->tags();

        if ($request->boolean('with_counts')) {
            $query->withCount('transactions');
        }

        $tags = $query->orderBy('name')->get();

        return TagResource::collection($tags);
    }

    public function show(Ledger $ledger, Tag $tag): TagResource
    {
        $this->authorize('view', $ledger);

        return new TagResource($tag);
    }

    public function store(Request $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $tag = $ledger->tags()->firstOrCreate(
            ['name' => $validated['name']],
            ['color' => $validated['color'] ?? null]
        );

        return (new TagResource($tag))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Ledger $ledger, Tag $tag): TagResource
    {
        $this->authorize('update', $ledger);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $tag->update($validated);

        return new TagResource($tag->fresh());
    }

    public function destroy(Ledger $ledger, Tag $tag): JsonResponse
    {
        $this->authorize('delete', $ledger);

        $tag->delete();

        return response()->json(null, 204);
    }
}
