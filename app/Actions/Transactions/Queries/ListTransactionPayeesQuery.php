<?php

namespace App\Actions\Transactions\Queries;

use App\Models\Ledger;
use Illuminate\Support\Collection;

class ListTransactionPayeesQuery
{
    /**
     * @return Collection<int, mixed>
     */
    public function __invoke(Ledger $ledger): Collection
    {
        return $ledger->payees()->orderBy('name')->get();
    }
}
