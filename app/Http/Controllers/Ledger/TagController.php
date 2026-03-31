<?php

namespace App\Http\Controllers\Ledger;

use App\Actions\Tags\Queries\GetTagPageQuery;
use App\Actions\Tags\UseCases\DeleteTagAction;
use App\Actions\Tags\UseCases\StoreTagAction;
use App\Actions\Tags\UseCases\UpdateTagAction;
use App\Data\Tags\Input\StoreTagData;
use App\Data\Tags\Input\UpdateTagData;
use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function index(Request $request, Ledger $ledger, GetTagPageQuery $getTagPage): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/tags/index', [
            'tags' => Inertia::defer(function () use ($ledger, $getTagPage) {
                return $getTagPage($ledger)->toInertiaProps()['tags'];
            }),
        ]);
    }

    public function store(Ledger $ledger, StoreTagData $data, StoreTagAction $storeTag): RedirectResponse
    {
        $storeTag($data);

        return to_route('ledgers.tags.index', $data->ledger)->with('success', 'Tag created.');
    }

    public function update(Ledger $ledger, Tag $tag, UpdateTagData $data, UpdateTagAction $updateTag): RedirectResponse
    {
        $updateTag($data);

        return to_route('ledgers.tags.index', $data->ledger)->with('success', 'Tag updated.');
    }

    public function destroy(Ledger $ledger, Tag $tag, DeleteTagAction $deleteTag): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $deleteTag($tag);

        return to_route('ledgers.tags.index', $ledger)->with('success', 'Tag deleted.');
    }
}
