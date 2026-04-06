<?php

namespace App\Actions\Accounts\Queries;

use App\Data\Accounts\Output\Web\AccountPageData;
use App\Models\Ledger;

class GetAccountPageQuery
{
    public function __construct(
        private readonly ListAccountsByTotalsQuery $listByTotals,
        private readonly GetNetWorthQuery $getNetWorth,
    ) {}

    public function __invoke(Ledger $ledger): AccountPageData
    {
        $accountTypes = $ledger->accountTypes()->orderBy('position')->get();

        return new AccountPageData(
            groups: ($this->listByTotals)($ledger),
            accountTypes: $accountTypes,
            netWorthFactory: fn () => ($this->getNetWorth)($ledger),
        );
    }
}
