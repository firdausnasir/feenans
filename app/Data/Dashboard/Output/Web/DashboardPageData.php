<?php

namespace App\Data\Dashboard\Output\Web;

use App\Data\Shared\Output\BaseOutputData;
use Closure;

class DashboardPageData extends BaseOutputData
{
    /**
     * @param  array{cycle_start: string, cycle_end: string, prev_cycle_start: string, prev_cycle_end: string, offset: int}  $cycle
     * @param  array{income: float, expense: float, net: float, prev_income: float, prev_expense: float}  $summary
     * @param  array<int, array<string, mixed>>  $accounts
     * @param  Closure(): array<int, array{date: string, expense: float, income: float}>  $dailyTrend
     * @param  Closure(): array<int, array{id: int|null, name: string, color: string|null, total: float, percentage: float}>  $topCategories
     * @param  Closure(): array<int, array<string, mixed>>  $recentTransactions
     * @param  Closure(): int  $uncategorizedCount
     * @param  Closure(): array{upcoming: array, due: array, missed: array}  $upcomingBills
     * @param  Closure(): array<int, array<string, mixed>>  $topBudgets
     */
    public function __construct(
        public array $cycle,
        public array $summary,
        public array $accounts,
        public Closure $dailyTrend,
        public Closure $topCategories,
        public Closure $recentTransactions,
        public Closure $uncategorizedCount,
        public Closure $upcomingBills,
        public Closure $topBudgets,
    ) {}
}
