<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Ledger;
use Carbon\CarbonImmutable;

class ReportService
{
    public function __construct(private readonly BudgetService $budgetService) {}

    /**
     * Get spending report with monthly trends, category/payee breakdowns, and summary.
     *
     * @param  array{date_from?: string, date_to?: string, account_id?: string|null}  $filters
     * @return array{monthly_trends: array, category_breakdown: array, payee_breakdown: array, income_category_breakdown: array, income_payee_breakdown: array, spending_heatmap: array, summary: array, date_range: array}
     */
    public function getSpendingReport(Ledger $ledger, array $filters = []): array
    {
        $today = CarbonImmutable::today();
        $currentCycle = $ledger->cycleBounds($today);

        $dateFrom = $filters['date_from'] ?? $currentCycle['start']->toDateString();
        $dateTo = $filters['date_to'] ?? $currentCycle['end']->toDateString();
        $accountId = $filters['account_id'] ?? null;

        $parsedFrom = CarbonImmutable::parse($dateFrom)->startOfDay();
        $parsedTo = CarbonImmutable::parse($dateTo)->endOfDay();

        $preset = $this->detectPreset($ledger, $dateFrom, $dateTo, $today);
        $monthlyTrend = $this->buildMonthlyTrend($ledger, $parsedFrom, $parsedTo, $dateFrom, $dateTo, $accountId);
        $categoryBreakdown = $this->buildCategoryBreakdownByType($ledger, $dateFrom, $dateTo, $accountId, TransactionType::Expense);
        $payeeBreakdown = $this->buildPayeeBreakdownByType($ledger, $dateFrom, $dateTo, $accountId, TransactionType::Expense);
        $incomeCategoryBreakdown = $this->buildCategoryBreakdownByType($ledger, $dateFrom, $dateTo, $accountId, TransactionType::Income);
        $incomePayeeBreakdown = $this->buildPayeeBreakdownByType($ledger, $dateFrom, $dateTo, $accountId, TransactionType::Income);
        $spendingHeatmap = $this->buildSpendingHeatmap($ledger, $dateFrom, $dateTo, $accountId);

        // Build summary from the trend data
        $totalIncome = array_sum(array_column($monthlyTrend, 'income'));
        $totalExpense = array_sum(array_column($monthlyTrend, 'expense'));

        return [
            'monthly_trends' => $monthlyTrend,
            'category_breakdown' => $categoryBreakdown,
            'payee_breakdown' => $payeeBreakdown,
            'income_category_breakdown' => $incomeCategoryBreakdown,
            'income_payee_breakdown' => $incomePayeeBreakdown,
            'spending_heatmap' => $spendingHeatmap,
            'summary' => [
                'total_income' => round($totalIncome, 2),
                'total_expense' => round($totalExpense, 2),
                'net' => round($totalIncome - $totalExpense, 2),
                'transaction_count' => $ledger->transactions()
                    ->whereIn('transaction_type', [TransactionType::Income->value, TransactionType::Expense->value])
                    ->whereBetween('transaction_date', [$dateFrom, $dateTo])
                    ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
                    ->count(),
            ],
            'date_range' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'preset' => $preset,
                'account_id' => $accountId,
            ],
        ];
    }

    /**
     * Get cash flow report with daily flow and upcoming bills.
     *
     * @param  array{date_from?: string, date_to?: string, account_id?: string|null}  $filters
     * @return array{daily_cash_flow: array, upcoming_bills: array, period_label: string}
     */
    public function getCashFlowReport(Ledger $ledger, array $filters = []): array
    {
        $today = CarbonImmutable::today();
        $currentCycle = $ledger->cycleBounds($today);

        $dateFrom = $filters['date_from'] ?? $currentCycle['start']->toDateString();
        $dateTo = $filters['date_to'] ?? $currentCycle['end']->toDateString();
        $accountId = $filters['account_id'] ?? null;

        $parsedFrom = CarbonImmutable::parse($dateFrom)->startOfDay();
        $parsedTo = CarbonImmutable::parse($dateTo)->endOfDay();

        $dailyFlow = $ledger->transactions()
            ->whereIn('transaction_type', [TransactionType::Income->value, TransactionType::Expense->value])
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
            ->selectRaw('transaction_date, transaction_type, SUM(amount) as total')
            ->groupBy('transaction_date', 'transaction_type')
            ->orderBy('transaction_date')
            ->get();

        // Key by date+type for O(1) lookups instead of O(n*m) scanning
        $dailyFlowByDate = [];

        foreach ($dailyFlow as $row) {
            $dateStr = $row->transaction_date->toDateString();
            $dailyFlowByDate[$dateStr] ??= ['income' => 0.0, 'expense' => 0.0];

            if ($row->transaction_type === TransactionType::Income) {
                $dailyFlowByDate[$dateStr]['income'] += (float) $row->total;
            } else {
                $dailyFlowByDate[$dateStr]['expense'] += abs((float) $row->total);
            }
        }

        $dailyCashFlow = [];
        $cursor = $parsedFrom;
        $cumulative = 0.0;

        while ($cursor->lte($parsedTo)) {
            $dateStr = $cursor->toDateString();
            $dayData = $dailyFlowByDate[$dateStr] ?? ['income' => 0.0, 'expense' => 0.0];

            $income = round($dayData['income'], 2);
            $expense = round($dayData['expense'], 2);
            $net = round($income - $expense, 2);
            $cumulative = round($cumulative + $net, 2);

            $dailyCashFlow[] = [
                'date' => $dateStr,
                'income' => $income,
                'expense' => $expense,
                'net' => $net,
                'cumulative' => $cumulative,
            ];

            $cursor = $cursor->addDay();
        }

        $upcomingBills = $ledger->bills()
            ->where('is_active', true)
            ->where('next_due_date', '>=', $today->toDateString())
            ->where('next_due_date', '<=', $today->addMonths(3)->toDateString())
            ->with(['account', 'category', 'payee'])
            ->orderBy('next_due_date')
            ->get()
            ->map(fn ($bill) => [
                'id' => $bill->id,
                'name' => $bill->name,
                'amount' => round((float) $bill->amount, 2),
                'transaction_type' => $bill->transaction_type,
                'next_due_date' => $bill->next_due_date->toDateString(),
                'account_name' => $bill->account?->name,
            ])
            ->values()
            ->toArray();

        return [
            'daily_cash_flow' => $dailyCashFlow,
            'upcoming_bills' => $upcomingBills,
            'period_label' => $parsedFrom->format('M d').' – '.$parsedTo->format('M d, Y'),
        ];
    }

    /**
     * Get budget performance stats for the current cycle.
     *
     * @param  array{period?: string}  $filters
     * @return array{budget_stats: array, period_label: string}
     */
    public function getBudgetPerformanceReport(Ledger $ledger, array $filters = []): array
    {
        $today = CarbonImmutable::today();
        $currentCycle = $ledger->cycleBounds($today);

        $stats = $this->budgetService->getBudgetsWithStats($ledger);

        $budgetStats = array_map(fn (array $stat): array => [
            'id' => $stat['id'],
            'category_name' => $stat['category_name'],
            'amount' => round((float) $stat['amount'], 2),
            'spent' => round((float) $stat['spent'], 2),
            'remaining' => round((float) $stat['amount'] - (float) $stat['spent'], 2),
            'percentage' => (float) $stat['amount'] > 0
                ? round(((float) $stat['spent'] / (float) $stat['amount']) * 100, 1)
                : 0,
            'period' => $stat['period'],
            'status' => $stat['status'],
        ], $stats);

        return [
            'budget_stats' => $budgetStats,
            'period_label' => $currentCycle['start']->format('M d').' – '.$currentCycle['end']->format('M d, Y'),
        ];
    }

    /**
     * Get financial health overview: net worth history, savings rate, current snapshot.
     *
     * @return array{net_worth_history: array, savings_rate_history: array, current_snapshot: array}
     */
    public function getFinancialHealthReport(Ledger $ledger): array
    {
        $accounts = $ledger->accounts()
            ->visible()
            ->with('accountType')
            ->withSum('transactions', 'amount')
            ->get();

        $today = CarbonImmutable::today();

        // Compute the 12 cycle boundaries
        $cycleBoundaries = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = $today->subMonths($i);
            $cycleBoundaries[] = $ledger->cycleBounds($date);
        }

        $earliestEnd = $cycleBoundaries[0]['end']->toDateString();
        $latestEnd = $cycleBoundaries[11]['end']->toDateString();

        // Fetch ALL transaction sums grouped by account_id and month-end boundary
        // We need cumulative sums up to each month-end, so fetch all transactions <= latestEnd
        $accountIds = $accounts->pluck('id')->toArray();

        $allTransactions = $ledger->transactions()
            ->whereIn('account_id', $accountIds)
            ->where('transaction_date', '<=', $latestEnd)
            ->selectRaw('account_id, transaction_date, SUM(amount) as total')
            ->groupBy('account_id', 'transaction_date')
            ->orderBy('transaction_date')
            ->get();

        // Build cumulative sums per account up to each cycle end date
        $transactionsByAccount = $allTransactions->groupBy('account_id');

        $netWorthHistory = [];

        foreach ($cycleBoundaries as $cycle) {
            $endDate = $cycle['end']->toDateString();
            $totalAssets = 0.0;
            $totalLiabilities = 0.0;

            foreach ($accounts as $account) {
                $accountTxns = $transactionsByAccount->get($account->id);
                $txnSum = 0.0;

                if ($accountTxns) {
                    $txnSum = (float) $accountTxns
                        ->filter(fn ($row) => $row->transaction_date->toDateString() <= $endDate)
                        ->sum('total');
                }

                $balance = (float) $account->initial_balance + $txnSum;

                if ($account->accountType->is_credit) {
                    $totalLiabilities += abs($balance);
                } else {
                    $totalAssets += $balance;
                }
            }

            $netWorthHistory[] = [
                'month' => $cycle['start']->format('Y-m'),
                'assets' => round($totalAssets, 2),
                'liabilities' => round($totalLiabilities, 2),
                'net_worth' => round($totalAssets - $totalLiabilities, 2),
            ];
        }

        // Savings rate history - single query grouped by month boundaries
        $earliestStart = $cycleBoundaries[0]['start']->toDateString();

        $savingsTransactions = $ledger->transactions()
            ->whereIn('transaction_type', [TransactionType::Income->value, TransactionType::Expense->value])
            ->whereBetween('transaction_date', [$earliestStart, $latestEnd])
            ->selectRaw('transaction_date, transaction_type, SUM(amount) as total')
            ->groupBy('transaction_date', 'transaction_type')
            ->get();

        $savingsRateHistory = [];

        foreach ($cycleBoundaries as $cycle) {
            $start = $cycle['start']->toDateString();
            $end = $cycle['end']->toDateString();

            $income = 0.0;
            $expense = 0.0;

            foreach ($savingsTransactions as $row) {
                $txDate = $row->transaction_date->toDateString();

                if ($txDate < $start || $txDate > $end) {
                    continue;
                }

                if ($row->transaction_type === TransactionType::Income) {
                    $income += (float) $row->total;
                } else {
                    $expense += abs((float) $row->total);
                }
            }

            $savingsRate = $income > 0 ? round((($income - $expense) / $income) * 100, 1) : 0;

            $savingsRateHistory[] = [
                'month' => $cycle['start']->format('Y-m'),
                'income' => round($income, 2),
                'expense' => round($expense, 2),
                'savings' => round($income - $expense, 2),
                'rate' => $savingsRate,
            ];
        }

        // Current snapshot - reuse the already-loaded withSum data
        $currentAssets = 0.0;
        $currentLiabilities = 0.0;

        foreach ($accounts as $account) {
            $balance = (float) $account->initial_balance + (float) ($account->transactions_sum_amount ?? 0);

            if ($account->accountType->is_credit) {
                $currentLiabilities += abs($balance);
            } else {
                $currentAssets += $balance;
            }
        }

        return [
            'net_worth_history' => $netWorthHistory,
            'savings_rate_history' => $savingsRateHistory,
            'current_snapshot' => [
                'assets' => round($currentAssets, 2),
                'liabilities' => round($currentLiabilities, 2),
                'net_worth' => round($currentAssets - $currentLiabilities, 2),
                'debt_to_asset_ratio' => $currentAssets > 0 ? round($currentLiabilities / $currentAssets, 2) : 0,
            ],
        ];
    }

    /**
     * Build comparison data between current and comparison periods.
     *
     * @return array{current_period: array, compare_period: array, categoryDeltas: array, trendOverlay: array, summary: array}
     */
    public function buildComparison(
        Ledger $ledger,
        string $currentFrom,
        string $currentTo,
        string $compareFrom,
        string $compareTo,
        ?string $accountId = null,
    ): array {
        $currentTotals = $this->periodExpenseTotals($ledger, $currentFrom, $currentTo, $accountId);
        $compareTotals = $this->periodExpenseTotals($ledger, $compareFrom, $compareTo, $accountId);

        $currentIncome = $this->periodIncomeTotals($ledger, $currentFrom, $currentTo, $accountId);
        $compareIncome = $this->periodIncomeTotals($ledger, $compareFrom, $compareTo, $accountId);

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

        $parsedCurrentFrom = CarbonImmutable::parse($currentFrom)->startOfDay();
        $parsedCurrentTo = CarbonImmutable::parse($currentTo)->endOfDay();
        $parsedCompareFrom = CarbonImmutable::parse($compareFrom)->startOfDay();
        $parsedCompareTo = CarbonImmutable::parse($compareTo)->endOfDay();

        $currentTrend = $this->buildMonthlyTrend($ledger, $parsedCurrentFrom, $parsedCurrentTo, $currentFrom, $currentTo, $accountId);
        $compareTrend = $this->buildMonthlyTrend($ledger, $parsedCompareFrom, $parsedCompareTo, $compareFrom, $compareTo, $accountId);

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

        $biggestChange = ! empty($categoryDeltas) ? $categoryDeltas[0] : null;

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
                'biggest_change' => $biggestChange,
            ],
        ];
    }

    /**
     * Build cycle-aware monthly trend buckets.
     *
     * @return array<int, array{month: string, income: float, expense: float, net: float}>
     */
    public function buildMonthlyTrend(
        Ledger $ledger,
        CarbonImmutable $parsedFrom,
        CarbonImmutable $parsedTo,
        string $dateFrom,
        string $dateTo,
        ?string $accountId = null,
    ): array {
        $buckets = [];
        $cursor = $ledger->cycleBounds($parsedFrom);

        while ($cursor['start']->toDateString() <= $dateTo) {
            $buckets[] = $cursor;
            $cursor = $ledger->cycleBounds($cursor['start']->addMonthNoOverflow());
        }

        if (empty($buckets)) {
            return [];
        }

        $transactionQuery = $ledger->transactions()
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->whereIn('transaction_type', [TransactionType::Income->value, TransactionType::Expense->value]);

        if ($accountId) {
            $transactionQuery->where('account_id', $accountId);
        }

        $transactions = $transactionQuery->get(['transaction_type', 'amount', 'transaction_date']);

        $result = [];

        foreach ($buckets as $bucket) {
            $bucketStart = $bucket['start']->toDateString();
            $bucketEnd = $bucket['end']->toDateString();

            $effectiveStart = max($bucketStart, $dateFrom);
            $effectiveEnd = min($bucketEnd, $dateTo);

            $income = 0.0;
            $expense = 0.0;

            foreach ($transactions as $transaction) {
                $txDate = $transaction->transaction_date->toDateString();

                if ($txDate < $effectiveStart || $txDate > $effectiveEnd) {
                    continue;
                }

                $amount = (float) $transaction->amount;

                if ($transaction->transaction_type === TransactionType::Income) {
                    $income += $amount;
                } elseif ($transaction->transaction_type === TransactionType::Expense) {
                    $expense += abs($amount);
                }
            }

            $result[] = [
                'month' => $bucket['start']->format('Y-m'),
                'income' => round($income, 2),
                'expense' => round($expense, 2),
                'net' => round($income - $expense, 2),
            ];
        }

        return $result;
    }

    /**
     * Build category breakdown by transaction type within the date range.
     *
     * @return array{items: array, parents: array}
     */
    public function buildCategoryBreakdownByType(Ledger $ledger, string $dateFrom, string $dateTo, ?string $accountId, TransactionType $type): array
    {
        $categoryQuery = $ledger->transactions()
            ->with('category.parent')
            ->where('transaction_type', $type->value)
            ->whereNotNull('category_id')
            ->whereBetween('transaction_date', [$dateFrom, $dateTo]);

        if ($accountId) {
            $categoryQuery->where('account_id', $accountId);
        }

        $transactions = $categoryQuery->get();

        if ($transactions->isEmpty()) {
            return ['items' => [], 'parents' => []];
        }

        $useAbsoluteAmounts = $type === TransactionType::Expense;

        $grouped = $transactions->groupBy('category_id');
        $categoryTotals = [];

        foreach ($grouped as $categoryId => $group) {
            $category = $group->first()->category;

            if (! $category) {
                continue;
            }

            $categoryTotals[$categoryId] = [
                'id' => $categoryId,
                'name' => $category->name,
                'color' => $category->color,
                'total' => round($group->sum(fn ($t) => $useAbsoluteAmounts ? abs((float) $t->amount) : (float) $t->amount), 2),
                'parent_id' => $category->parent_id,
            ];
        }

        $parentAggregated = [];

        foreach ($categoryTotals as $item) {
            if ($item['parent_id'] === null) {
                if (! isset($parentAggregated[$item['id']])) {
                    $parentAggregated[$item['id']] = [
                        'id' => $item['id'],
                        'name' => $item['name'],
                        'color' => $item['color'],
                        'total' => 0.0,
                        'parent_id' => null,
                        'children' => [],
                    ];
                }

                $parentAggregated[$item['id']]['total'] += $item['total'];
            } else {
                $parentId = $item['parent_id'];

                if (! isset($parentAggregated[$parentId])) {
                    $parentCategory = $grouped->flatten()->first(
                        fn ($t) => $t->category && $t->category->parent_id === null && $t->category_id === $parentId
                    )?->category;

                    if (! $parentCategory) {
                        $parentCategory = $grouped->flatten()->first(
                            fn ($t) => $t->category && $t->category->parent_id === $parentId
                        )?->category?->parent;
                    }

                    $parentAggregated[$parentId] = [
                        'id' => $parentId,
                        'name' => $parentCategory?->name ?? 'Unknown',
                        'color' => $parentCategory?->color,
                        'total' => 0.0,
                        'parent_id' => null,
                        'children' => [],
                    ];
                }

                $parentAggregated[$parentId]['total'] += $item['total'];
                $parentAggregated[$parentId]['children'][] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'color' => $item['color'],
                    'total' => $item['total'],
                ];
            }
        }

        foreach ($parentAggregated as &$parent) {
            $parent['total'] = round($parent['total'], 2);
            usort($parent['children'], fn ($a, $b) => $b['total'] <=> $a['total']);
        }
        unset($parent);

        $allItems = array_values($categoryTotals);
        $grandTotal = array_sum(array_column($allItems, 'total'));

        $result = [];

        foreach ($allItems as $item) {
            $item['percentage'] = $grandTotal > 0
                ? round(($item['total'] / $grandTotal) * 100, 2)
                : 0.0;
            $item['children'] = [];
            $result[] = $item;
        }

        $parentItems = array_values($parentAggregated);
        $parentGrandTotal = array_sum(array_column($parentItems, 'total'));

        foreach ($parentItems as &$parentItem) {
            $parentItem['percentage'] = $parentGrandTotal > 0
                ? round(($parentItem['total'] / $parentGrandTotal) * 100, 2)
                : 0.0;

            foreach ($parentItem['children'] as &$child) {
                $child['percentage'] = $parentItem['total'] > 0
                    ? round(($child['total'] / $parentItem['total']) * 100, 2)
                    : 0.0;
            }
            unset($child);
        }
        unset($parentItem);

        usort($result, fn ($a, $b) => $b['total'] <=> $a['total']);
        usort($parentItems, fn ($a, $b) => $b['total'] <=> $a['total']);

        return [
            'items' => array_values($result),
            'parents' => array_values($parentItems),
        ];
    }

    /**
     * Build payee breakdown by transaction type within the date range.
     *
     * @return array<int, array{id: int|null, name: string, total: float, percentage: float}>
     */
    public function buildPayeeBreakdownByType(Ledger $ledger, string $dateFrom, string $dateTo, ?string $accountId, TransactionType $type): array
    {
        $query = $ledger->transactions()
            ->where('transaction_type', $type->value)
            ->whereBetween('transaction_date', [$dateFrom, $dateTo]);

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        $transactions = $query->with('payee')->get();

        if ($transactions->isEmpty()) {
            return [];
        }

        $useAbsoluteAmounts = $type === TransactionType::Expense;

        $grouped = $transactions->groupBy(fn ($t) => $t->payee_id ?? 'none');
        $items = [];

        foreach ($grouped as $key => $group) {
            $total = round($group->sum(fn ($t) => $useAbsoluteAmounts ? abs((float) $t->amount) : (float) $t->amount), 2);
            $payee = $key !== 'none' ? $group->first()->payee : null;

            $items[] = [
                'id' => $payee?->id,
                'name' => $payee?->name ?? 'No payee',
                'total' => $total,
            ];
        }

        usort($items, fn ($a, $b) => $b['total'] <=> $a['total']);

        $grandTotal = array_sum(array_column($items, 'total'));

        foreach ($items as &$item) {
            $item['percentage'] = $grandTotal > 0
                ? round(($item['total'] / $grandTotal) * 100, 2)
                : 0.0;
        }
        unset($item);

        return array_slice($items, 0, 10);
    }

    /**
     * Build daily spending amounts for the heatmap.
     *
     * @return array<int, array{date: string, amount: float}>
     */
    public function buildSpendingHeatmap(Ledger $ledger, string $dateFrom, string $dateTo, ?string $accountId = null): array
    {
        $query = $ledger->transactions()
            ->where('transaction_type', TransactionType::Expense->value)
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->selectRaw('transaction_date, SUM(ABS(amount)) as total')
            ->groupBy('transaction_date');

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        return $query->get()
            ->map(fn ($row) => [
                'date' => $row->transaction_date->toDateString(),
                'amount' => round((float) $row->total, 2),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Detect which preset matches the given date range.
     */
    public function detectPreset(Ledger $ledger, string $dateFrom, string $dateTo, CarbonImmutable $today): string
    {
        $currentCycle = $ledger->cycleBounds($today);

        if ($dateFrom === $currentCycle['start']->toDateString() && $dateTo === $currentCycle['end']->toDateString()) {
            return 'this_month';
        }

        $lastMonthCycle = $ledger->cycleBounds($currentCycle['start']->subDay());
        if ($dateFrom === $lastMonthCycle['start']->toDateString() && $dateTo === $lastMonthCycle['end']->toDateString()) {
            return 'last_month';
        }

        $threeMonthsBack = $currentCycle['start'];
        for ($i = 0; $i < 3; $i++) {
            $threeMonthsBack = $ledger->cycleBounds($threeMonthsBack->subDay())['start'];
        }
        if ($dateFrom === $threeMonthsBack->toDateString() && $dateTo === $currentCycle['end']->toDateString()) {
            return 'last_3_months';
        }

        $sixMonthsBack = $currentCycle['start'];
        for ($i = 0; $i < 6; $i++) {
            $sixMonthsBack = $ledger->cycleBounds($sixMonthsBack->subDay())['start'];
        }
        if ($dateFrom === $sixMonthsBack->toDateString() && $dateTo === $currentCycle['end']->toDateString()) {
            return 'last_6_months';
        }

        $janFirst = CarbonImmutable::create($today->year, 1, 1);
        $janCycle = $ledger->cycleBounds($janFirst);
        if ($dateFrom === $janCycle['start']->toDateString() && $dateTo === $currentCycle['end']->toDateString()) {
            return 'this_year';
        }

        return 'custom';
    }

    /**
     * Get total expenses and per-category breakdown for a date range.
     *
     * @return array{total: float, byCategory: array<string, float>}
     */
    public function periodExpenseTotals(Ledger $ledger, string $dateFrom, string $dateTo, ?string $accountId = null): array
    {
        $query = $ledger->transactions()
            ->with('category.parent')
            ->where('transaction_type', TransactionType::Expense->value)
            ->whereBetween('transaction_date', [$dateFrom, $dateTo]);

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        $transactions = $query->get();

        $total = 0.0;
        $byCategory = [];

        foreach ($transactions as $transaction) {
            $amount = abs((float) $transaction->amount);
            $total += $amount;

            $categoryName = 'Uncategorised';

            if ($transaction->category) {
                $categoryName = $transaction->category->parent
                    ? $transaction->category->parent->name
                    : $transaction->category->name;
            }

            $byCategory[$categoryName] = ($byCategory[$categoryName] ?? 0.0) + $amount;
        }

        return ['total' => round($total, 2), 'byCategory' => $byCategory];
    }

    /**
     * Get total income for a date range.
     */
    public function periodIncomeTotals(Ledger $ledger, string $dateFrom, string $dateTo, ?string $accountId = null): float
    {
        $query = $ledger->transactions()
            ->where('transaction_type', TransactionType::Income->value)
            ->whereBetween('transaction_date', [$dateFrom, $dateTo]);

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        return round((float) $query->sum('amount'), 2);
    }
}
