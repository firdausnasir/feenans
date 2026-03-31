<?php

namespace App\Actions\Tags\Queries;

use App\Data\Tags\Output\TagData;
use App\Models\Ledger;
use Illuminate\Support\Collection;

class ListTagsQuery
{
    /**
     * @return Collection<int, TagData>
     */
    public function __invoke(Ledger $ledger): Collection
    {
        return $ledger->tags()
            ->withCount('transactions')
            ->orderBy('name')
            ->get()
            ->map(fn ($tag) => TagData::fromModel($tag));
    }
}
