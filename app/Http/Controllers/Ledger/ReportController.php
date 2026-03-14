<?php

namespace App\Http\Controllers\Ledger;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReportFilterRequest;
use App\Models\Ledger;
use App\Services\TransactionService;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(ReportFilterRequest $request, Ledger $ledger, TransactionService $transactionService): Response
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

        // --- Statement Cycles (2-year lookback, independent of trend date range) ---
        $cyclesFrom = $parsedTo->subYears(2);
        $statementCycles = $this->buildStatementCycles($ledger, $transactionService, $cyclesFrom, $parsedTo);

        // --- Credit Accounts ---
        $creditAccounts = $ledger->accounts()
            ->whereNotNull('statement_day')
            ->get(['id', 'name', 'statement_day'])
            ->values();

        // --- All accounts for filter dropdown ---
        $allAccounts = $ledger->accounts()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('ledgers/reports/index', [
            'ledger' => $ledger,
            'monthlyTrend' => $monthlyTrend,
            'categoryBreakdown' => $categoryBreakdown,
            'payeeBreakdown' => $payeeBreakdown,
            'statementCycles' => $statementCycles,
            'creditAccounts' => $creditAccounts,
            'allAccounts' => $allAccounts,
            'dateRange' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'preset' => $preset,
                'account_id' => $accountId,
            ],
        ]);
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
            return [];
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
     * Build statement cycles for all credit accounts (constrained to a date range).
     *
     * @return array<int, array{account_id: int, account_name: string, start_date: string, end_date: string, total: float}>
     */
    private function buildStatementCycles(
        Ledger $ledger,
        TransactionService $transactionService,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array {
        $creditAccounts = $ledger->accounts()
            ->whereNotNull('statement_day')
            ->with(['transactions' => fn ($q) => $q->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])->orderBy('transaction_date')])
            ->get();

        $cycles = [];

        foreach ($creditAccounts as $account) {
            foreach ($account->transactions as $transaction) {
                [$start, $end] = $transactionService->statementCycleBounds(
                    $account,
                    CarbonImmutable::parse($transaction->transaction_date),
                );

                $key = $account->id.'-'.$start->toDateString().'-'.$end->toDateString();

                if (! isset($cycles[$key])) {
                    $cycles[$key] = [
                        'account_id' => $account->id,
                        'account_name' => $account->name,
                        'start_date' => $start->toDateString(),
                        'end_date' => $end->toDateString(),
                        'total' => 0.0,
                    ];
                }

                $cycles[$key]['total'] += (float) $transaction->amount;
            }
        }

        foreach ($cycles as &$cycle) {
            $cycle['total'] = round($cycle['total'], 2);
        }
        unset($cycle);

        return array_values($cycles);
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
}
