<?php

namespace App\Actions\Budgets\Queries;

use App\Models\Budget;
use App\Models\Ledger;

class GetBudgetSpentQuery
{
    public function __construct(private readonly GetBudgetPeriodBoundsQuery $getBudgetPeriodBounds) {}

    public function __invoke(Budget $budget, Ledger $ledger): float
    {
        [$start, $end] = ($this->getBudgetPeriodBounds)($budget, $ledger);

        $query = $ledger->transactions()
            ->where('transaction_date', '>=', $start->toDateString())
            ->where('transaction_date', '<=', $end->toDateString())
            ->where('amount', '<', 0);

        if ($budget->category_id !== null) {
            $query->where('category_id', $budget->category_id);
        }

        return (float) abs($query->sum('amount'));
    }
}
