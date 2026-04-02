<?php

namespace App\Actions\Budgets\UseCases;

use App\Data\Budgets\Input\UpdateBudgetData;
use App\Models\Budget;

class UpdateBudgetAction
{
    public function __invoke(UpdateBudgetData $data): Budget
    {
        $data->budget->update([
            'category_id' => $data->category_id,
            'amount' => $data->amount,
            'period' => $data->period,
            'start_date' => $data->start_date,
            'end_date' => $data->end_date,
            'rollover' => $data->rollover,
        ]);

        return $data->budget->fresh();
    }
}
