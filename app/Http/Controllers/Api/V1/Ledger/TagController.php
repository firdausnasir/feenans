<?php

namespace App\Http\Controllers\Api\V1\Ledger;

use App\Actions\Tags\Queries\ListTagsQuery;
use App\Actions\Tags\UseCases\DeleteTagAction;
use App\Actions\Tags\UseCases\StoreTagAction;
use App\Actions\Tags\UseCases\UpdateTagAction;
use App\Data\Tags\Input\StoreTagData;
use App\Data\Tags\Input\UpdateTagData;
use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;

class TagController extends Controller
{
    public function index(Ledger $ledger, ListTagsQuery $listTags): JsonResponse
    {
        $this->authorize('view', $ledger);

        return response()->json([
            'data' => $listTags($ledger)->map->toArray()->values()->all(),
        ]);
    }

    public function store(Ledger $ledger, StoreTagData $data, StoreTagAction $storeTag): JsonResponse
    {
        return response()->json([
            'data' => $storeTag($data)->toArray(),
        ], 201);
    }

    public function update(Ledger $ledger, Tag $tag, UpdateTagData $data, UpdateTagAction $updateTag): JsonResponse
    {
        return response()->json([
            'data' => $updateTag($data)->toArray(),
        ]);
    }

    public function destroy(Ledger $ledger, Tag $tag, DeleteTagAction $deleteTag): JsonResponse
    {
        $this->authorize('delete', $ledger);

        return response()->json([
            'data' => $deleteTag($tag)->toArray(),
        ]);
    }
}
