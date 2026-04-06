<?php

namespace App\Actions\Categories\Queries;

use App\Data\Categories\Output\CategoryData;
use App\Models\Ledger;
use Illuminate\Support\Collection;

class ListCategoriesQuery
{
    /**
     * @return Collection<int, CategoryData>
     */
    public function __invoke(Ledger $ledger): Collection
    {
        return $ledger->categories()
            ->withCount('transactions')
            ->with(['children' => fn ($query) => $query
                ->withCount('transactions')
                ->orderBy('position')])
            ->parents()
            ->orderBy('position')
            ->get()
            ->map(fn ($category) => CategoryData::fromModel($category));
    }
}
