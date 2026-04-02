<?php

namespace App\Actions\Reports\Queries;

use App\Data\Reports\Input\GetCashFlowPageData;
use App\Models\Ledger;

class GetCashFlowPageQuery
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(Ledger $ledger, GetCashFlowPageData $input): array
    {
        return [];
    }
}
