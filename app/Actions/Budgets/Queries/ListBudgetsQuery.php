<?php

namespace App\Actions\Budgets\Queries;

use App\Data\Budgets\Output\BudgetData;
use App\Models\Budget;
use App\Models\Ledger;
use Illuminate\Support\Collection;

class ListBudgetsQuery
{
    public function __construct(
        private readonly GetBudgetPeriodBoundsQuery $getBudgetPeriodBounds,
        private readonly GetBudgetSpendMapQuery $getBudgetSpendMap,
    ) {}

    /**
     * @return Collection<int, BudgetData>
     */
    public function __invoke(Ledger $ledger): Collection
    {
        $budgets = $ledger->budgets()
            ->with('category')
            ->where('is_active', true)
            ->get();

        $spentByBudgetId = ($this->getBudgetSpendMap)($budgets, $ledger);

        return $budgets->map(function (Budget $budget) use ($ledger, $spentByBudgetId): BudgetData {
            [$periodStart, $periodEnd] = ($this->getBudgetPeriodBounds)($budget, $ledger);

            return BudgetData::fromModel(
                $budget,
                $spentByBudgetId[$budget->id] ?? 0.0,
                $periodStart,
                $periodEnd,
            );
        });
    }
}
