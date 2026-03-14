<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
use App\Models\Ledger;
use App\Models\Tag;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagController extends Controller
{
    public function index(Ledger $ledger): AnonymousResourceCollection
    {
        $this->authorize('view', $ledger);

        $tags = $ledger->tags()
            ->orderBy('name')
            ->get();

        return TagResource::collection($tags);
    }

    public function show(Ledger $ledger, Tag $tag): TagResource
    {
        $this->authorize('view', $ledger);

        return new TagResource($tag);
    }
}
