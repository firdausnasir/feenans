<?php

namespace App\Actions\Bills\Queries;

use App\Data\Bills\Output\Web\BillAccountOptionData;
use App\Models\Account;
use App\Models\Ledger;
use Illuminate\Support\Collection;

class GetBillAccountOptionsQuery
{
    /**
     * @return Collection<int, BillAccountOptionData>
     */
    public function __invoke(Ledger $ledger): Collection
    {
        return $ledger->accounts()
            ->visible()
            ->orderBy('name')
            ->get(['id', 'ledger_id', 'name', 'color'])
            ->map(fn (Account $account) => BillAccountOptionData::fromModel($account));
    }
}
