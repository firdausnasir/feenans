<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Ledger;
use Carbon\CarbonImmutable;

/**
 * Report aggregates intentionally keep expense-side values absolute for current consumers.
 * Signed-number API normalization is deferred until the report clients can migrate together.
 */
class ReportService
{
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
