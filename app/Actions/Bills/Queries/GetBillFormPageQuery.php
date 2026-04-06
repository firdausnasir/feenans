<?php

namespace App\Actions\Bills\Queries;

use App\Actions\Categories\Queries\ListCategoriesQuery;
use App\Actions\Payees\Queries\ListPayeesQuery;
use App\Data\Bills\Output\Web\BillData;
use App\Data\Bills\Output\Web\BillPageData;
use App\Models\Bill;
use App\Models\Ledger;

class GetBillFormPageQuery
{
    public function __construct(
        private readonly GetBillAccountOptionsQuery $getBillAccountOptions,
        private readonly ListCategoriesQuery $listCategories,
        private readonly ListPayeesQuery $listPayees,
    ) {}

    public function __invoke(Ledger $ledger, ?Bill $bill = null): BillPageData
    {
        return new BillPageData(
            accounts: ($this->getBillAccountOptions)($ledger),
            categories: ($this->listCategories)($ledger),
            payees: ($this->listPayees)($ledger),
            bill: $bill !== null
                ? BillData::fromModel($bill->load(['account', 'toAccount', 'category', 'payee']))
                : null,
        );
    }
}
