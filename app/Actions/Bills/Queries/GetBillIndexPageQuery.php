<?php

namespace App\Actions\Bills\Queries;

use App\Actions\Categories\Queries\ListCategoriesQuery;
use App\Actions\Payees\Queries\ListPayeesQuery;
use App\Data\Bills\Output\Web\BillData;
use App\Data\Bills\Output\Web\BillPageData;
use App\Models\Ledger;

class GetBillIndexPageQuery
{
    public function __construct(
        private readonly GetBillAccountOptionsQuery $getBillAccountOptions,
        private readonly ListCategoriesQuery $listCategories,
        private readonly ListPayeesQuery $listPayees,
        private readonly ListBillsQuery $listBills,
    ) {}

    public function __invoke(Ledger $ledger): BillPageData
    {
        return new BillPageData(
            accounts: ($this->getBillAccountOptions)($ledger),
            categories: ($this->listCategories)($ledger),
            payees: ($this->listPayees)($ledger),
            billsFactory: fn () => ($this->listBills)($ledger)
                ->map(fn (BillData $bill) => $bill->toArray())
                ->values()
                ->all(),
        );
    }
}
