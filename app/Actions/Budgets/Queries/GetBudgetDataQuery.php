<?php

namespace App\Actions\Budgets\Queries;

use App\Data\Budgets\Output\BudgetData;
use App\Models\Budget;
use App\Models\Ledger;

class GetBudgetDataQuery
{
    public function __construct(
        private readonly GetBudgetPeriodBoundsQuery $getBudgetPeriodBounds,
        private readonly GetBudgetSpentQuery $getBudgetSpent,
    ) {}

    public function __invoke(Budget $budget, Ledger $ledger): BudgetData
    {
        $budget->loadMissing('category');

        [$periodStart, $periodEnd] = ($this->getBudgetPeriodBounds)($budget, $ledger);
        $spent = ($this->getBudgetSpent)($budget, $ledger);

        return BudgetData::fromModel($budget, $spent, $periodStart, $periodEnd);
    }
}
