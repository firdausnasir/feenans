<?php

namespace App\Actions\Transactions\Queries;

use App\Models\Ledger;
use Illuminate\Support\Collection;

class ListTransactionCategoriesQuery
{
    /**
     * @return Collection<int, mixed>
     */
    public function __invoke(Ledger $ledger): Collection
    {
        return $ledger->categories()->orderBy('position')->get();
    }
}
