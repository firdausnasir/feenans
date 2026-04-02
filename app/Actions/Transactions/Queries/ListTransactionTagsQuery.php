<?php

namespace App\Actions\Transactions\Queries;

use App\Models\Ledger;
use Illuminate\Support\Collection;

class ListTransactionTagsQuery
{
    /**
     * @return Collection<int, mixed>
     */
    public function __invoke(Ledger $ledger): Collection
    {
        return $ledger->tags()->orderBy('name')->get();
    }
}
