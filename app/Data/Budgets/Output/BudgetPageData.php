<?php

namespace App\Data\Budgets\Output;

use App\Data\Shared\Output\BaseOutputData;
use Illuminate\Support\Collection;

class BudgetPageData extends BaseOutputData
{
    /**
     * @param  Collection<int, BudgetData>  $budgets
     */
    public function __construct(public Collection $budgets) {}

    /**
     * @return array{budgets: array<int, array<string, mixed>>}
     */
    public function toInertiaProps(): array
    {
        return [
            'budgets' => $this->budgets->map(fn (BudgetData $budget) => $budget->toArray())->values()->all(),
        ];
    }
}
