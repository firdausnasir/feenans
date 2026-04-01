<?php

namespace App\Actions\Payees\Queries;

use App\Data\Payees\Output\PayeeData;
use App\Models\Ledger;
use App\Models\Payee;
use Illuminate\Support\Collection;

class ListPayeesQuery
{
    /**
     * @return Collection<int, PayeeData>
     */
    public function __invoke(Ledger $ledger, ?string $search = null): Collection
    {
        return $ledger->payees()
            ->withCount('transactions')
            ->when($search, function ($query, string $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->get()
            ->map(fn (Payee $payee) => PayeeData::fromModel($payee));
    }
}
