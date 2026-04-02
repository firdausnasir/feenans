<?php

namespace App\Actions\Reports\Queries;

use App\Models\Ledger;
use App\Services\ReportService;
use Carbon\CarbonImmutable;

class GetReportComparisonQuery
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    /**
     * @return array{current_period: array{from: string, to: string}, compare_period: array{from: string, to: string}, categoryDeltas: array<int, array<string, mixed>>, trendOverlay: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    public function __invoke(
        Ledger $ledger,
        string $currentFrom,
        string $currentTo,
        string $compareFrom,
        string $compareTo,
        ?string $accountId = null,
    ): array {
        $currentTotals = $this->reportService->periodExpenseTotals($ledger, $currentFrom, $currentTo, $accountId);
        $compareTotals = $this->reportService->periodExpenseTotals($ledger, $compareFrom, $compareTo, $accountId);

        $currentIncome = $this->reportService->periodIncomeTotals($ledger, $currentFrom, $currentTo, $accountId);
        $compareIncome = $this->reportService->periodIncomeTotals($ledger, $compareFrom, $compareTo, $accountId);

        $allCategoryNames = array_unique(array_merge(
            array_keys($currentTotals['byCategory']),
            array_keys($compareTotals['byCategory']),
        ));

        $categoryDeltas = [];

        foreach ($allCategoryNames as $categoryName) {
            $currentAmount = $currentTotals['byCategory'][$categoryName] ?? 0.0;
            $compareAmount = $compareTotals['byCategory'][$categoryName] ?? 0.0;
            $delta = $currentAmount - $compareAmount;
            $percentageChange = $compareAmount > 0
                ? round((($currentAmount - $compareAmount) / $compareAmount) * 100, 1)
                : ($currentAmount > 0 ? 100.0 : 0.0);

            $categoryDeltas[] = [
                'name' => $categoryName,
                'current' => round($currentAmount, 2),
                'previous' => round($compareAmount, 2),
                'delta' => round($delta, 2),
                'percentage_change' => $percentageChange,
            ];
        }

        usort($categoryDeltas, fn ($a, $b) => abs($b['delta']) <=> abs($a['delta']));

        $currentTrend = $this->reportService->buildMonthlyTrend(
            $ledger,
            CarbonImmutable::parse($currentFrom)->startOfDay(),
            CarbonImmutable::parse($currentTo)->endOfDay(),
            $currentFrom,
            $currentTo,
            $accountId,
        );
        $compareTrend = $this->reportService->buildMonthlyTrend(
            $ledger,
            CarbonImmutable::parse($compareFrom)->startOfDay(),
            CarbonImmutable::parse($compareTo)->endOfDay(),
            $compareFrom,
            $compareTo,
            $accountId,
        );

        $maxLength = max(count($currentTrend), count($compareTrend));
        $trendOverlay = [];

        for ($i = 0; $i < $maxLength; $i++) {
            $current = $currentTrend[$i] ?? null;
            $compare = $compareTrend[$i] ?? null;

            $trendOverlay[] = [
                'index' => $i + 1,
                'current_month' => $current['month'] ?? null,
                'compare_month' => $compare['month'] ?? null,
                'current_expense' => $current['expense'] ?? 0,
                'compare_expense' => $compare['expense'] ?? 0,
                'current_income' => $current['income'] ?? 0,
                'compare_income' => $compare['income'] ?? 0,
            ];
        }

        $totalCurrentExpense = $currentTotals['total'];
        $totalCompareExpense = $compareTotals['total'];
        $expenseDelta = $totalCurrentExpense - $totalCompareExpense;
        $expensePercentageChange = $totalCompareExpense > 0
            ? round((($totalCurrentExpense - $totalCompareExpense) / $totalCompareExpense) * 100, 1)
            : ($totalCurrentExpense > 0 ? 100.0 : 0.0);

        $totalCurrentIncome = $currentIncome;
        $totalCompareIncome = $compareIncome;
        $incomeDelta = $totalCurrentIncome - $totalCompareIncome;
        $incomePercentageChange = $totalCompareIncome > 0
            ? round((($totalCurrentIncome - $totalCompareIncome) / $totalCompareIncome) * 100, 1)
            : ($totalCurrentIncome > 0 ? 100.0 : 0.0);

        return [
            'current_period' => ['from' => $currentFrom, 'to' => $currentTo],
            'compare_period' => ['from' => $compareFrom, 'to' => $compareTo],
            'categoryDeltas' => $categoryDeltas,
            'trendOverlay' => $trendOverlay,
            'summary' => [
                'current_expense' => round($totalCurrentExpense, 2),
                'compare_expense' => round($totalCompareExpense, 2),
                'expense_delta' => round($expenseDelta, 2),
                'expense_percentage_change' => $expensePercentageChange,
                'current_income' => round($totalCurrentIncome, 2),
                'compare_income' => round($totalCompareIncome, 2),
                'income_delta' => round($incomeDelta, 2),
                'income_percentage_change' => $incomePercentageChange,
                'biggest_change' => $categoryDeltas !== [] ? $categoryDeltas[0] : null,
            ],
        ];
    }
}
