<?php

namespace App\Actions\Budgets\Queries;

use App\Data\Budgets\Output\BudgetPageData;
use App\Models\Ledger;

class GetBudgetPageQuery
{
    public function __construct(private readonly ListBudgetsQuery $listBudgets) {}

    public function __invoke(Ledger $ledger): BudgetPageData
    {
        return new BudgetPageData(budgets: ($this->listBudgets)($ledger));
    }
}
