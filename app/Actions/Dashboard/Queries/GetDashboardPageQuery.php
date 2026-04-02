<?php

namespace App\Actions\Dashboard\Queries;

use App\Actions\Accounts\Queries\ListAccountsByTypeQuery;
use App\Actions\Bills\Queries\ListUpcomingBillsQuery;
use App\Actions\Budgets\Queries\ListBudgetsQuery;
use App\Data\Dashboard\Output\Web\DashboardPageData;
use App\Enums\TransactionType;
use App\Http\Resources\TransactionResource;
use App\Models\Ledger;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class GetDashboardPageQuery
{
    public function __construct(
        private readonly ListAccountsByTypeQuery $listByType,
        private readonly ListUpcomingBillsQuery $listUpcomingBills,
        private readonly ListBudgetsQuery $listBudgets,
    ) {}

    public function __invoke(Ledger $ledger, int $offset = 0): DashboardPageData
    {
        $cycle = $this->computeCycle($ledger, $offset);
        $dateFrom = $cycle['cycle_start'];
        $dateTo = $cycle['cycle_end'];
        $prevFrom = $cycle['prev_cycle_start'];
        $prevTo = $cycle['prev_cycle_end'];

        return new DashboardPageData(
            cycle: $cycle,
            summary: $this->buildSummary($ledger, $dateFrom, $dateTo, $prevFrom, $prevTo),
            accounts: ($this->listByType)($ledger),
            dailyTrend: fn () => $this->buildDailyTrend($ledger, $dateFrom, $dateTo),
            topCategories: fn () => $this->buildTopCategories($ledger, $dateFrom, $dateTo),
            recentTransactions: fn () => $this->buildRecentTransactions($ledger, $dateFrom, $dateTo),
            uncategorizedCount: fn () => $this->buildUncategorizedCount($ledger, $dateFrom, $dateTo),
            upcomingBills: fn () => $this->buildUpcomingBills($ledger),
            topBudgets: fn () => $this->buildTopBudgets($ledger),
        );
    }

    /**
     * @return array{cycle_start: string, cycle_end: string, prev_cycle_start: string, prev_cycle_end: string, offset: int}
     */
    private function computeCycle(Ledger $ledger, int $offset): array
    {
        $now = CarbonImmutable::now();
        $ref = $offset !== 0 ? $now->addMonthsNoOverflow($offset) : $now;
        ['start' => $start, 'end' => $end] = $ledger->cycleBounds($ref);

        $prevRef = $ref->subMonthNoOverflow();
        ['start' => $prevStart, 'end' => $prevEnd] = $ledger->cycleBounds($prevRef);

        return [
            'cycle_start' => $start->toDateString(),
            'cycle_end' => $end->toDateString(),
            'prev_cycle_start' => $prevStart->toDateString(),
            'prev_cycle_end' => $prevEnd->toDateString(),
            'offset' => $offset,
        ];
    }

    /**
     * @return array{income: float, expense: float, net: float, prev_income: float, prev_expense: float}
     */
    private function buildSummary(Ledger $ledger, string $dateFrom, string $dateTo, string $prevFrom, string $prevTo): array
    {
        $incomeType = TransactionType::Income->value;
        $expenseType = TransactionType::Expense->value;

        $current = $ledger->transactions()
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->selectRaw('COALESCE(SUM(CASE WHEN transaction_type = ? THEN amount END), 0) as income', [$incomeType])
            ->selectRaw('COALESCE(SUM(CASE WHEN transaction_type = ? THEN amount END), 0) as expense', [$expenseType])
            ->first();

        $income = (float) $current->income;
        $expense = (float) $current->expense;

        $prev = $ledger->transactions()
            ->whereBetween('transaction_date', [$prevFrom, $prevTo])
            ->selectRaw('COALESCE(SUM(CASE WHEN transaction_type = ? THEN amount END), 0) as income', [$incomeType])
            ->selectRaw('COALESCE(SUM(CASE WHEN transaction_type = ? THEN amount END), 0) as expense', [$expenseType])
            ->first();

        return [
            'income' => round((float) $income, 2),
            'expense' => round((float) $expense, 2),
            'net' => round($income + $expense, 2),
            'prev_income' => round((float) $prev->income, 2),
            'prev_expense' => round((float) $prev->expense, 2),
        ];
    }

    /**
     * @return array<int, array{date: string, expense: float, income: float}>
     */
    private function buildDailyTrend(Ledger $ledger, string $dateFrom, string $dateTo): array
    {
        $dailyTotals = $ledger->transactions()
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->whereNotNull('category_id')
            ->select('transaction_date', 'transaction_type', DB::raw('SUM(amount) as total'))
            ->groupBy('transaction_date', 'transaction_type')
            ->get()
            ->groupBy(fn ($row) => $row->transaction_date->format('Y-m-d'));

        $from = CarbonImmutable::parse($dateFrom);
        $to = CarbonImmutable::parse($dateTo);
        $trendEnd = $to->isBefore(now()) ? $to : now();

        return collect(CarbonPeriod::create($from, $trendEnd))
            ->map(function ($day) use ($dailyTotals) {
                $key = $day->format('Y-m-d');
                $dayData = $dailyTotals->get($key, collect());

                return [
                    'date' => $key,
                    'expense' => round(abs((float) ($dayData
                        ->firstWhere('transaction_type', TransactionType::Expense)
                        ?->total ?? 0)), 2),
                    'income' => round((float) ($dayData
                        ->firstWhere('transaction_type', TransactionType::Income)
                        ?->total ?? 0), 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int|null, name: string, color: string|null, total: float, percentage: float}>
     */
    private function buildTopCategories(Ledger $ledger, string $dateFrom, string $dateTo): array
    {
        $topCategories = $ledger->transactions()
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->where('transaction_type', TransactionType::Expense->value)
            ->whereNotNull('category_id')
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->groupBy('category_id')
            ->orderByRaw('SUM(amount) ASC')
            ->limit(5)
            ->get();

        $totalExpense = $topCategories->sum(fn ($row) => abs((float) $row->total));

        $categoryLookup = $ledger->categories()
            ->whereIn('id', $topCategories->pluck('category_id')->filter()->all())
            ->get()
            ->keyBy('id');

        return $topCategories
            ->map(function ($row) use ($categoryLookup, $totalExpense) {
                $category = $row->category_id ? $categoryLookup->get($row->category_id) : null;
                $absoluteTotal = round(abs((float) $row->total), 2);

                return [
                    'id' => $category?->id,
                    'name' => $category?->name ?? 'Uncategorized',
                    'color' => $category?->color,
                    'total' => $absoluteTotal,
                    'percentage' => $totalExpense > 0
                        ? round(($absoluteTotal / $totalExpense) * 100, 1)
                        : 0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildRecentTransactions(Ledger $ledger, string $dateFrom, string $dateTo): array
    {
        $transactions = $ledger->transactions()
            ->with(['account', 'category', 'payee', 'tags'])
            ->withCount('splits')
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return TransactionResource::collection($transactions)->resolve();
    }

    private function buildUncategorizedCount(Ledger $ledger, string $dateFrom, string $dateTo): int
    {
        return $ledger->transactions()
            ->whereNull('category_id')
            ->where('transaction_type', '!=', TransactionType::Transfer)
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->count();
    }

    /**
     * @return array{upcoming: array<int, array<string, mixed>>, due: array<int, array<string, mixed>>, missed: array<int, array<string, mixed>>}
     */
    private function buildUpcomingBills(Ledger $ledger): array
    {
        return ($this->listUpcomingBills)($ledger, 3);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildTopBudgets(Ledger $ledger): array
    {
        return ($this->listBudgets)($ledger)
            ->sortByDesc(fn ($budget) => $budget->percentage)
            ->take(3)
            ->values()
            ->map->toArray()
            ->all();
    }
}
