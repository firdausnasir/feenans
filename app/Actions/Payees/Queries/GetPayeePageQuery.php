<?php

namespace App\Actions\Payees\Queries;

use App\Data\Payees\Output\PayeePageData;
use App\Models\Ledger;

class GetPayeePageQuery
{
    public function __construct(private ListPayeesQuery $listPayees) {}

    public function __invoke(Ledger $ledger, ?string $search = null): PayeePageData
    {
        return new PayeePageData(payees: ($this->listPayees)($ledger, $search));
    }
}
