<?php

namespace App\Actions\Budgets\UseCases;

use App\Models\Budget;

class DeleteBudgetAction
{
    public function __invoke(Budget $budget): Budget
    {
        $budget->loadMissing('category');
        $deletedBudget = clone $budget;

        $budget->delete();

        return $deletedBudget;
    }
}
