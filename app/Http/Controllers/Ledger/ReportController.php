<?php

namespace App\Http\Controllers\Ledger;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReportFilterRequest;
use App\Models\Ledger;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReportController extends Controller
{
    public function index(ReportFilterRequest $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        $accountId = $request->input('account_id');

        // Resolve date range: default to current cycle month
        $today = CarbonImmutable::today();
        $currentCycle = $ledger->cycleBounds($today);

        $dateFrom = $request->input('date_from', $currentCycle['start']->toDateString());
        $dateTo = $request->input('date_to', $currentCycle['end']->toDateString());

        $parsedFrom = CarbonImmutable::parse($dateFrom)->startOfDay();
        $parsedTo = CarbonImmutable::parse($dateTo)->endOfDay();

        // Detect preset
        $preset = $this->detectPreset($ledger, $dateFrom, $dateTo, $today);

        // --- Monthly Trend (cycle-aware) ---
        $monthlyTrend = $this->buildMonthlyTrend($ledger, $parsedFrom, $parsedTo, $dateFrom, $dateTo, $accountId);

        // --- Category Breakdown (expenses only, within date range) ---
        $categoryBreakdown = $this->buildCategoryBreakdown($ledger, $dateFrom, $dateTo, $accountId);

        // --- Payee Breakdown (expenses only, within date range) ---
        $payeeBreakdown = $this->buildPayeeBreakdown($ledger, $dateFrom, $dateTo, $accountId);

        // --- Income Category Breakdown (income only, within date range) ---
        $incomeCategoryBreakdown = $this->buildIncomeCategoryBreakdown($ledger, $dateFrom, $dateTo, $accountId);

        // --- Income Payee Breakdown (income only, within date range) ---
        $incomePayeeBreakdown = $this->buildIncomePayeeBreakdown($ledger, $dateFrom, $dateTo, $accountId);

        // --- Spending Heatmap ---
        $spendingHeatmap = $this->buildSpendingHeatmap($ledger, $dateFrom, $dateTo, $accountId);

        // --- Comparison period (optional) ---
        $comparison = null;
        $compareStart = $request->input('compare_start');
        $compareEnd = $request->input('compare_end');

        if ($compareStart && $compareEnd) {
            $comparison = $this->buildComparison(
                $ledger,
                $dateFrom,
                $dateTo,
                $compareStart,
                $compareEnd,
                $accountId,
            );
        }

        // --- All accounts for filter dropdown ---
        $allAccounts = $ledger->accounts()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('ledgers/reports/index', [
            'ledger' => $ledger,
            'monthlyTrend' => $monthlyTrend,
            'categoryBreakdown' => $categoryBreakdown,
            'payeeBreakdown' => $payeeBreakdown,
            'incomeCategoryBreakdown' => $incomeCategoryBreakdown,
            'incomePayeeBreakdown' => $incomePayeeBreakdown,
            'spendingHeatmap' => $spendingHeatmap,
            'allAccounts' => $allAccounts,
            'comparison' => $comparison,
            'dateRange' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'preset' => $preset,
                'account_id' => $accountId,
                'compare_start' => $compareStart,
                'compare_end' => $compareEnd,
            ],
        ]);
    }

    public function financialHealth(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        $accounts = $ledger->accounts()->visible()->with('accountType')->get();

        // Net worth over time: monthly snapshots going back 12 months
        $today = CarbonImmutable::today();
        $netWorthHistory = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = $today->subMonths($i);
            $cycleBounds = $ledger->cycleBounds($date);
            $endDate = $cycleBounds['end']->toDateString();

            $totalAssets = 0.0;
            $totalLiabilities = 0.0;

            foreach ($accounts as $account) {
                $balance = (float) $account->initial_balance + (float) $account->transactions()
                    ->where('transaction_date', '<=', $endDate)
                    ->sum('amount');

                // Determine if asset or liability based on account type
                if (str_contains(strtolower($account->accountType->name ?? ''), 'liabilit') ||
                    str_contains(strtolower($account->accountType->name ?? ''), 'credit')) {
                    $totalLiabilities += abs($balance);
                } else {
                    $totalAssets += $balance;
                }
            }

            $netWorthHistory[] = [
                'month' => $cycleBounds['start']->format('Y-m'),
                'assets' => round($totalAssets, 2),
                'liabilities' => round($totalLiabilities, 2),
                'net_worth' => round($totalAssets - $totalLiabilities, 2),
            ];
        }

        // Savings rate: (income - expense) / income per month for last 12 months
        $savingsRateHistory = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = $today->subMonths($i);
            $cycleBounds = $ledger->cycleBounds($date);
            $start = $cycleBounds['start']->toDateString();
            $end = $cycleBounds['end']->toDateString();

            $income = (float) $ledger->transactions()
                ->where('transaction_type', TransactionType::Income->value)
                ->whereBetween('transaction_date', [$start, $end])
                ->sum('amount');

            $expense = abs((float) $ledger->transactions()
                ->where('transaction_type', TransactionType::Expense->value)
                ->whereBetween('transaction_date', [$start, $end])
                ->sum('amount'));

            $savingsRate = $income > 0 ? round((($income - $expense) / $income) * 100, 1) : 0;

            $savingsRateHistory[] = [
                'month' => $cycleBounds['start']->format('Y-m'),
                'income' => round($income, 2),
                'expense' => round($expense, 2),
                'savings' => round($income - $expense, 2),
                'rate' => $savingsRate,
            ];
        }

        // Current snapshot
        $currentAssets = 0.0;
        $currentLiabilities = 0.0;

        foreach ($accounts as $account) {
            $balance = (float) $account->initial_balance + (float) $account->transactions()->sum('amount');

            if (str_contains(strtolower($account->accountType->name ?? ''), 'liabilit') ||
                str_contains(strtolower($account->accountType->name ?? ''), 'credit')) {
                $currentLiabilities += abs($balance);
            } else {
                $currentAssets += $balance;
            }
        }

        return Inertia::render('ledgers/reports/financial-health', [
            'ledger' => $ledger,
            'netWorthHistory' => $netWorthHistory,
            'savingsRateHistory' => $savingsRateHistory,
            'currentSnapshot' => [
                'assets' => round($currentAssets, 2),
                'liabilities' => round($currentLiabilities, 2),
                'net_worth' => round($currentAssets - $currentLiabilities, 2),
                'debt_to_asset_ratio' => $currentAssets > 0 ? round($currentLiabilities / $currentAssets, 2) : 0,
            ],
        ]);
    }

    public function budgetPerformance(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        $budgets = $ledger->budgets()->with('category')->where('is_active', true)->get();
        $today = CarbonImmutable::today();
        $currentCycle = $ledger->cycleBounds($today);

        $budgetStats = [];

        foreach ($budgets as $budget) {
            $start = $currentCycle['start']->toDateString();
            $end = $currentCycle['end']->toDateString();

            $query = $ledger->transactions()
                ->where('transaction_type', TransactionType::Expense->value)
                ->whereBetween('transaction_date', [$start, $end]);

            if ($budget->category_id) {
                $query->where(function ($q) use ($budget) {
                    $q->where('category_id', $budget->category_id)
                        ->orWhereHas('category', fn ($sub) => $sub->where('parent_id', $budget->category_id));
                });
            }

            $spent = abs((float) $query->sum('amount'));
            $percentage = $budget->amount > 0 ? round(($spent / (float) $budget->amount) * 100, 1) : 0;

            $budgetStats[] = [
                'id' => $budget->id,
                'category_name' => $budget->category?->name ?? 'Overall',
                'amount' => round((float) $budget->amount, 2),
                'spent' => round($spent, 2),
                'remaining' => round((float) $budget->amount - $spent, 2),
                'percentage' => $percentage,
                'period' => $budget->period,
                'status' => $percentage >= 100 ? 'over' : ($percentage >= 90 ? 'danger' : ($percentage >= 75 ? 'warning' : 'good')),
            ];
        }

        return Inertia::render('ledgers/reports/budget-performance', [
            'ledger' => $ledger,
            'budgetStats' => $budgetStats,
            'periodLabel' => $currentCycle['start']->format('M d').' – '.$currentCycle['end']->format('M d, Y'),
        ]);
    }

    public function cashFlow(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        $today = CarbonImmutable::today();
        $currentCycle = $ledger->cycleBounds($today);
        $start = $currentCycle['start']->toDateString();
        $end = $currentCycle['end']->toDateString();

        // Daily cash flow for current cycle
        $dailyFlow = $ledger->transactions()
            ->whereIn('transaction_type', [TransactionType::Income->value, TransactionType::Expense->value])
            ->whereBetween('transaction_date', [$start, $end])
            ->selectRaw('transaction_date, transaction_type, SUM(amount) as total')
            ->groupBy('transaction_date', 'transaction_type')
            ->orderBy('transaction_date')
            ->get();

        $dailyCashFlow = [];
        $cursor = $currentCycle['start'];

        while ($cursor->lte($currentCycle['end'])) {
            $dateStr = $cursor->toDateString();
            $income = 0.0;
            $expense = 0.0;

            foreach ($dailyFlow as $row) {
                if ($row->transaction_date->toDateString() === $dateStr) {
                    if ($row->transaction_type === TransactionType::Income->value) {
                        $income += (float) $row->total;
                    } else {
                        $expense += abs((float) $row->total);
                    }
                }
            }

            $dailyCashFlow[] = [
                'date' => $dateStr,
                'income' => round($income, 2),
                'expense' => round($expense, 2),
                'net' => round($income - $expense, 2),
            ];

            $cursor = $cursor->addDay();
        }

        // Upcoming recurring transactions
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

        return Inertia::render('ledgers/reports/cash-flow', [
            'ledger' => $ledger,
            'dailyCashFlow' => $dailyCashFlow,
            'upcomingBills' => $upcomingBills,
            'periodLabel' => $currentCycle['start']->format('M d').' – '.$currentCycle['end']->format('M d, Y'),
        ]);
    }

    public function exportPdf(Request $request, Ledger $ledger): HttpResponse
    {
        $this->authorize('view', $ledger);

        $month = $request->query('month', CarbonImmutable::today()->format('Y-m'));
        $parsedMonth = CarbonImmutable::createFromFormat('Y-m', $month);

        $dateFrom = $parsedMonth->startOfMonth();
        $dateTo = $parsedMonth->endOfMonth();

        $dateFromStr = $dateFrom->toDateString();
        $dateToStr = $dateTo->toDateString();

        // Income total
        $incomeTotal = (float) $ledger->transactions()
            ->where('transaction_type', TransactionType::Income->value)
            ->whereBetween('transaction_date', [$dateFromStr, $dateToStr])
            ->sum('amount');

        // Expense total (absolute)
        $expenseTotal = (float) $ledger->transactions()
            ->where('transaction_type', TransactionType::Expense->value)
            ->whereBetween('transaction_date', [$dateFromStr, $dateToStr])
            ->sum('amount');
        $expenseTotal = abs($expenseTotal);

        $netTotal = round($incomeTotal - $expenseTotal, 2);

        // Transaction count
        $transactionCount = $ledger->transactions()
            ->whereIn('transaction_type', [TransactionType::Income->value, TransactionType::Expense->value])
            ->whereBetween('transaction_date', [$dateFromStr, $dateToStr])
            ->count();

        // Category breakdown (expenses only)
        $categoryTransactions = $ledger->transactions()
            ->with('category.parent')
            ->where('transaction_type', TransactionType::Expense->value)
            ->whereNotNull('category_id')
            ->whereBetween('transaction_date', [$dateFromStr, $dateToStr])
            ->get();

        $categoryTotals = [];

        foreach ($categoryTransactions as $transaction) {
            $categoryName = 'Uncategorised';

            if ($transaction->category) {
                $categoryName = $transaction->category->parent
                    ? $transaction->category->parent->name
                    : $transaction->category->name;
            }

            $categoryTotals[$categoryName] = ($categoryTotals[$categoryName] ?? 0.0)
                + abs((float) $transaction->amount);
        }

        arsort($categoryTotals);

        $categoryBreakdown = [];

        foreach ($categoryTotals as $name => $total) {
            $percentage = $expenseTotal > 0
                ? round(($total / $expenseTotal) * 100, 1)
                : 0.0;

            $categoryBreakdown[] = [
                'name' => $name,
                'total' => round($total, 2),
                'percentage' => $percentage,
            ];
        }

        $pdf = Pdf::loadView('reports.monthly-pdf', [
            'ledgerName' => $ledger->name,
            'monthLabel' => $parsedMonth->format('F Y'),
            'incomeTotal' => round($incomeTotal, 2),
            'expenseTotal' => round($expenseTotal, 2),
            'netTotal' => $netTotal,
            'transactionCount' => $transactionCount,
            'categoryBreakdown' => $categoryBreakdown,
            'generatedAt' => CarbonImmutable::now()->format('d M Y, H:i'),
        ]);

        $filename = 'report-'.$ledger->name.'-'.$month.'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Build cycle-aware monthly trend buckets.
     *
     * @return array<int, array{month: string, income: float, expense: float, net: float}>
     */
    private function buildMonthlyTrend(
        Ledger $ledger,
        CarbonImmutable $parsedFrom,
        CarbonImmutable $parsedTo,
        string $dateFrom,
        string $dateTo,
        ?string $accountId = null,
    ): array {
        // Generate cycle buckets within the date range
        $buckets = [];
        $cursor = $ledger->cycleBounds($parsedFrom);

        while ($cursor['start']->toDateString() <= $dateTo) {
            $buckets[] = $cursor;
            $cursor = $ledger->cycleBounds($cursor['start']->addMonthNoOverflow());
        }

        if (empty($buckets)) {
            return [];
        }

        // Fetch all transactions in the overall range once
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

            // Intersect bucket with requested date range
            $effectiveStart = max($bucketStart, $dateFrom);
            $effectiveEnd = min($bucketEnd, $dateTo);

            $income = 0.0;
            $expense = 0.0;

            foreach ($transactions as $transaction) {
                // transaction_date is cast to Carbon date
                $txDate = $transaction->transaction_date->toDateString();

                if ($txDate < $effectiveStart || $txDate > $effectiveEnd) {
                    continue;
                }

                $amount = (float) $transaction->amount;

                // transaction_type is cast to TransactionType enum
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
     * Build category breakdown for expenses within the date range.
     *
     * Returns both individual category totals and parent-aggregated totals.
     * Each item includes a `children` array when the category is a parent
     * that has subcategory spending, enabling the frontend to display
     * both "parent only" and "with subcategories" views.
     *
     * @return array<int, array{id: int, name: string, color: string|null, total: float, percentage: float, parent_id: int|null, children: array}>
     */
    private function buildCategoryBreakdown(Ledger $ledger, string $dateFrom, string $dateTo, ?string $accountId = null): array
    {
        $categoryQuery = $ledger->transactions()
            ->with('category.parent')
            ->where('transaction_type', TransactionType::Expense->value)
            ->whereNotNull('category_id')
            ->whereBetween('transaction_date', [$dateFrom, $dateTo]);

        if ($accountId) {
            $categoryQuery->where('account_id', $accountId);
        }

        $transactions = $categoryQuery->get();

        if ($transactions->isEmpty()) {
            return ['items' => [], 'parents' => []];
        }

        // Build per-category totals (subcategories and parents that have direct transactions)
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
                'total' => round($group->sum(fn ($t) => abs((float) $t->amount)), 2),
                'parent_id' => $category->parent_id,
            ];
        }

        // Build parent-aggregated view: roll subcategory totals up to parents
        $parentAggregated = [];

        foreach ($categoryTotals as $item) {
            if ($item['parent_id'] === null) {
                // Direct parent category spending
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
                // Subcategory: aggregate into parent
                $parentId = $item['parent_id'];

                if (! isset($parentAggregated[$parentId])) {
                    // Look up parent info from the loaded relationship
                    $parentCategory = $grouped->flatten()->first(
                        fn ($t) => $t->category && $t->category->parent_id === null && $t->category_id === $parentId
                    )?->category;

                    // If parent has no direct transactions, find it via subcategory's parent relation
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

        // Round parent totals and sort children
        foreach ($parentAggregated as &$parent) {
            $parent['total'] = round($parent['total'], 2);
            usort($parent['children'], fn ($a, $b) => $b['total'] <=> $a['total']);
        }
        unset($parent);

        // Compute percentages and flatten into a single array:
        // parent items (with children embedded), plus individual subcategory items
        $allItems = array_values($categoryTotals);
        $grandTotal = array_sum(array_column($allItems, 'total'));

        // Add percentage + children to each item
        $result = [];

        foreach ($allItems as $item) {
            $item['percentage'] = $grandTotal > 0
                ? round(($item['total'] / $grandTotal) * 100, 2)
                : 0.0;
            $item['children'] = [];
            $result[] = $item;
        }

        // Append parent-aggregated entries (for frontend "parent only" view)
        $parentItems = array_values($parentAggregated);
        $parentGrandTotal = array_sum(array_column($parentItems, 'total'));

        foreach ($parentItems as &$parentItem) {
            $parentItem['percentage'] = $parentGrandTotal > 0
                ? round(($parentItem['total'] / $parentGrandTotal) * 100, 2)
                : 0.0;

            // Compute child percentages relative to parent total
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
     * Build payee breakdown for expenses within the date range.
     *
     * @return array<int, array{id: int|null, name: string, total: float, percentage: float}>
     */
    private function buildPayeeBreakdown(Ledger $ledger, string $dateFrom, string $dateTo, ?string $accountId = null): array
    {
        $query = $ledger->transactions()
            ->where('transaction_type', TransactionType::Expense->value)
            ->whereBetween('transaction_date', [$dateFrom, $dateTo]);

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        $transactions = $query->with('payee')->get();

        if ($transactions->isEmpty()) {
            return [];
        }

        $grouped = $transactions->groupBy(fn ($t) => $t->payee_id ?? 'none');
        $items = [];

        foreach ($grouped as $key => $group) {
            $total = round($group->sum(fn ($t) => abs((float) $t->amount)), 2);
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
     * Build category breakdown for income within the date range.
     *
     * Returns both individual category totals and parent-aggregated totals,
     * following the same structure as the expense category breakdown.
     *
     * @return array{items: array, parents: array}
     */
    private function buildIncomeCategoryBreakdown(Ledger $ledger, string $dateFrom, string $dateTo, ?string $accountId = null): array
    {
        $categoryQuery = $ledger->transactions()
            ->with('category.parent')
            ->where('transaction_type', TransactionType::Income->value)
            ->whereNotNull('category_id')
            ->whereBetween('transaction_date', [$dateFrom, $dateTo]);

        if ($accountId) {
            $categoryQuery->where('account_id', $accountId);
        }

        $transactions = $categoryQuery->get();

        if ($transactions->isEmpty()) {
            return ['items' => [], 'parents' => []];
        }

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
                'total' => round($group->sum(fn ($t) => (float) $t->amount), 2),
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
     * Build payee breakdown for income within the date range.
     *
     * @return array<int, array{id: int|null, name: string, total: float, percentage: float}>
     */
    private function buildIncomePayeeBreakdown(Ledger $ledger, string $dateFrom, string $dateTo, ?string $accountId = null): array
    {
        $query = $ledger->transactions()
            ->where('transaction_type', TransactionType::Income->value)
            ->whereBetween('transaction_date', [$dateFrom, $dateTo]);

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        $transactions = $query->with('payee')->get();

        if ($transactions->isEmpty()) {
            return [];
        }

        $grouped = $transactions->groupBy(fn ($t) => $t->payee_id ?? 'none');
        $items = [];

        foreach ($grouped as $key => $group) {
            $total = round($group->sum(fn ($t) => (float) $t->amount), 2);
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
     * Build comparison data between the current period and a comparison period.
     *
     * Returns category deltas, monthly trend overlay, and summary stats.
     *
     * @return array{current: array, previous: array, categoryDeltas: array, trendOverlay: array, summary: array}
     */
    private function buildComparison(
        Ledger $ledger,
        string $currentFrom,
        string $currentTo,
        string $compareFrom,
        string $compareTo,
        ?string $accountId = null,
    ): array {
        // Gather expense totals for both periods
        $currentTotals = $this->periodExpenseTotals($ledger, $currentFrom, $currentTo, $accountId);
        $compareTotals = $this->periodExpenseTotals($ledger, $compareFrom, $compareTo, $accountId);

        // Gather income totals for both periods
        $currentIncome = $this->periodIncomeTotals($ledger, $currentFrom, $currentTo, $accountId);
        $compareIncome = $this->periodIncomeTotals($ledger, $compareFrom, $compareTo, $accountId);

        // Category deltas: merge all category names from both periods
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

        // Sort by absolute delta descending
        usort($categoryDeltas, fn ($a, $b) => abs($b['delta']) <=> abs($a['delta']));

        // Build monthly trend overlay
        $parsedCurrentFrom = CarbonImmutable::parse($currentFrom)->startOfDay();
        $parsedCurrentTo = CarbonImmutable::parse($currentTo)->endOfDay();
        $parsedCompareFrom = CarbonImmutable::parse($compareFrom)->startOfDay();
        $parsedCompareTo = CarbonImmutable::parse($compareTo)->endOfDay();

        $currentTrend = $this->buildMonthlyTrend($ledger, $parsedCurrentFrom, $parsedCurrentTo, $currentFrom, $currentTo, $accountId);
        $compareTrend = $this->buildMonthlyTrend($ledger, $parsedCompareFrom, $parsedCompareTo, $compareFrom, $compareTo, $accountId);

        // Align trends by index (month 1, month 2, etc.) for overlay
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

        // Summary
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

        // Find the biggest category change for the summary sentence
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
     * Get total expenses and per-category breakdown for a date range.
     *
     * @return array{total: float, byCategory: array<string, float>}
     */
    private function periodExpenseTotals(Ledger $ledger, string $dateFrom, string $dateTo, ?string $accountId = null): array
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

            // Use parent category name if available for consistent grouping
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
    private function periodIncomeTotals(Ledger $ledger, string $dateFrom, string $dateTo, ?string $accountId = null): float
    {
        $query = $ledger->transactions()
            ->where('transaction_type', TransactionType::Income->value)
            ->whereBetween('transaction_date', [$dateFrom, $dateTo]);

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        return round((float) $query->sum('amount'), 2);
    }

    /**
     * Detect which preset matches the given date range.
     */
    private function detectPreset(Ledger $ledger, string $dateFrom, string $dateTo, CarbonImmutable $today): string
    {
        $currentCycle = $ledger->cycleBounds($today);

        // This month
        if ($dateFrom === $currentCycle['start']->toDateString() && $dateTo === $currentCycle['end']->toDateString()) {
            return 'this_month';
        }

        // Last month
        $lastMonthCycle = $ledger->cycleBounds($currentCycle['start']->subDay());
        if ($dateFrom === $lastMonthCycle['start']->toDateString() && $dateTo === $lastMonthCycle['end']->toDateString()) {
            return 'last_month';
        }

        // Last 3 months
        $threeMonthsBack = $currentCycle['start'];
        for ($i = 0; $i < 3; $i++) {
            $threeMonthsBack = $ledger->cycleBounds($threeMonthsBack->subDay())['start'];
        }
        if ($dateFrom === $threeMonthsBack->toDateString() && $dateTo === $currentCycle['end']->toDateString()) {
            return 'last_3_months';
        }

        // Last 6 months
        $sixMonthsBack = $currentCycle['start'];
        for ($i = 0; $i < 6; $i++) {
            $sixMonthsBack = $ledger->cycleBounds($sixMonthsBack->subDay())['start'];
        }
        if ($dateFrom === $sixMonthsBack->toDateString() && $dateTo === $currentCycle['end']->toDateString()) {
            return 'last_6_months';
        }

        // This year: from Jan cycle start to current cycle end
        $janFirst = CarbonImmutable::create($today->year, 1, 1);
        $janCycle = $ledger->cycleBounds($janFirst);
        if ($dateFrom === $janCycle['start']->toDateString() && $dateTo === $currentCycle['end']->toDateString()) {
            return 'this_year';
        }

        return 'custom';
    }

    /**
     * Build daily spending amounts for the heatmap.
     *
     * @return array<int, array{date: string, amount: float}>
     */
    private function buildSpendingHeatmap(Ledger $ledger, string $dateFrom, string $dateTo, ?string $accountId = null): array
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
}
