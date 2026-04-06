<?php

namespace App\Actions\Reports\Queries;

use App\Data\Reports\Input\ReportFiltersData;
use App\Data\Reports\Output\Web\SpendingReportData;
use App\Enums\TransactionType;
use App\Models\Ledger;
use App\Services\ReportService;
use Carbon\CarbonImmutable;

class GetSpendingReportDataQuery
{
    public function __construct(
        private readonly ReportService $reportService,
        private readonly ResolveReportDateRangeQuery $resolveReportDateRange,
        private readonly GetReportComparisonQuery $getReportComparison,
    ) {}

    public function __invoke(Ledger $ledger, ReportFiltersData $input): SpendingReportData
    {
        $dateRange = ($this->resolveReportDateRange)($ledger, $input);
        $accountId = $dateRange->account_id;

        $monthlyTrend = $this->reportService->buildMonthlyTrend(
            $ledger,
            CarbonImmutable::parse($dateRange->date_from)->startOfDay(),
            CarbonImmutable::parse($dateRange->date_to)->endOfDay(),
            $dateRange->date_from,
            $dateRange->date_to,
            $accountId,
        );
        $categoryBreakdown = $this->reportService->buildCategoryBreakdownByType(
            $ledger,
            $dateRange->date_from,
            $dateRange->date_to,
            $accountId,
            TransactionType::Expense,
        );
        $payeeBreakdown = $this->reportService->buildPayeeBreakdownByType(
            $ledger,
            $dateRange->date_from,
            $dateRange->date_to,
            $accountId,
            TransactionType::Expense,
        );
        $incomeCategoryBreakdown = $this->reportService->buildCategoryBreakdownByType(
            $ledger,
            $dateRange->date_from,
            $dateRange->date_to,
            $accountId,
            TransactionType::Income,
        );
        $incomePayeeBreakdown = $this->reportService->buildPayeeBreakdownByType(
            $ledger,
            $dateRange->date_from,
            $dateRange->date_to,
            $accountId,
            TransactionType::Income,
        );
        $spendingHeatmap = $this->reportService->buildSpendingHeatmap(
            $ledger,
            $dateRange->date_from,
            $dateRange->date_to,
            $accountId,
        );

        $totalIncome = array_sum(array_column($monthlyTrend, 'income'));
        $totalExpense = array_sum(array_column($monthlyTrend, 'expense'));

        return new SpendingReportData(
            monthly_trends: $monthlyTrend,
            category_breakdown: $categoryBreakdown,
            payee_breakdown: $payeeBreakdown,
            income_category_breakdown: $incomeCategoryBreakdown,
            income_payee_breakdown: $incomePayeeBreakdown,
            spending_heatmap: $spendingHeatmap,
            summary: [
                'total_income' => round($totalIncome, 2),
                'total_expense' => round($totalExpense, 2),
                'net' => round($totalIncome - $totalExpense, 2),
                'transaction_count' => $ledger->transactions()
                    ->whereIn('transaction_type', [TransactionType::Income->value, TransactionType::Expense->value])
                    ->whereBetween('transaction_date', [$dateRange->date_from, $dateRange->date_to])
                    ->when($accountId, fn ($query) => $query->where('account_id', $accountId))
                    ->count(),
            ],
            date_range: $dateRange,
            comparison: $input->hasComparison()
                ? ($this->getReportComparison)(
                    $ledger,
                    $dateRange->date_from,
                    $dateRange->date_to,
                    $input->compare_start,
                    $input->compare_end,
                    $accountId,
                )
                : null,
        );
    }
}
