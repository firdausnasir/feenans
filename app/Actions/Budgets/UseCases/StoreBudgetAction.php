<?php

namespace App\Actions\Budgets\UseCases;

use App\Data\Budgets\Input\StoreBudgetData;
use App\Models\Budget;

class StoreBudgetAction
{
    public function __invoke(StoreBudgetData $data): Budget
    {
        return $data->ledger->budgets()->create([
            'category_id' => $data->category_id,
            'amount' => $data->amount,
            'period' => $data->period,
            'start_date' => $data->start_date,
            'end_date' => $data->end_date,
            'is_active' => true,
            'rollover' => $data->rollover,
        ]);
    }
}
