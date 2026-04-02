<?php

namespace App\Actions\Reports\Queries;

use App\Actions\Budgets\Queries\ListBudgetsQuery;
use App\Data\Reports\Input\BudgetPerformanceFiltersData;
use App\Data\Reports\Output\Web\BudgetPerformanceReportData;
use App\Models\Ledger;
use Carbon\CarbonImmutable;

class GetBudgetPerformanceReportDataQuery
{
    public function __construct(private readonly ListBudgetsQuery $listBudgets) {}

    public function __invoke(Ledger $ledger, BudgetPerformanceFiltersData $input): BudgetPerformanceReportData
    {
        $today = CarbonImmutable::today();
        $currentCycle = $ledger->cycleBounds($today);

        $stats = ($this->listBudgets)($ledger)->map->toArray()->all();

        $budgetStats = array_map(fn (array $stat): array => [
            'id' => $stat['id'],
            'category_name' => $stat['category_name'],
            'amount' => (float) round((float) $stat['amount'], 2),
            'spent' => (float) round((float) $stat['spent'], 2),
            'remaining' => (float) round((float) $stat['remaining'], 2),
            'percentage' => (float) $stat['percentage'],
            'period' => $stat['period'],
            'status' => $stat['status'],
        ], $stats);

        return new BudgetPerformanceReportData(
            budget_stats: $budgetStats,
            period_label: $currentCycle['start']->format('M d').' – '.$currentCycle['end']->format('M d, Y'),
        );
    }
}
