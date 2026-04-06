<?php

namespace App\Actions\Budgets\Queries;

use App\Models\Budget;
use App\Models\Ledger;
use Carbon\CarbonImmutable;

class GetBudgetPeriodBoundsQuery
{
    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function __invoke(Budget $budget, Ledger $ledger): array
    {
        $today = CarbonImmutable::today();
        $cycleBounds = $ledger->cycleBounds($today);

        return match ($budget->period) {
            'weekly' => [$today->startOfWeek(), $today->endOfWeek()],
            'yearly' => [$today->startOfYear(), $today->endOfYear()],
            default => [$cycleBounds['start'], $cycleBounds['end']],
        };
    }
}
