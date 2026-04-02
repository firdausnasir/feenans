<?php

namespace App\Actions\Reports\Queries;

use App\Data\Reports\Input\GetBudgetPerformancePageData;
use App\Models\Ledger;

class GetBudgetPerformancePageQuery
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(Ledger $ledger, GetBudgetPerformancePageData $input): array
    {
        return [];
    }
}
