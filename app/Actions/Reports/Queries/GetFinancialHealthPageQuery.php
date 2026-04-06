<?php

namespace App\Actions\Reports\Queries;

use App\Data\Reports\Input\GetFinancialHealthPageData;
use App\Models\Ledger;

class GetFinancialHealthPageQuery
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(Ledger $ledger, GetFinancialHealthPageData $input): array
    {
        return [];
    }
}
