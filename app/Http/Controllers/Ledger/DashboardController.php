<?php

namespace App\Http\Controllers\Ledger;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Services\BillService;
use App\Services\BudgetService;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Ledger $ledger, Request $request, BillService $billService, BudgetService $budgetService): Response
    {
        $this->authorize('view', $ledger);
        $request->session()->put('current_ledger_id', $ledger->id);

        $cycleOffset = (int) $request->get('cycle_offset', 0);
        $now = now();
        $referenceDate = $now;

        // Navigate to a different cycle using the offset
        if ($cycleOffset !== 0) {
            $referenceDate = $now->addMonthsNoOverflow($cycleOffset);
        }

        ['start' => $start, 'end' => $end] = $ledger->cycleBounds($referenceDate);

        $cycleTransactions = $ledger->transactions()
            ->whereBetween('transaction_date', [$start, $end]);

        $income = (float) (clone $cycleTransactions)
            ->where('transaction_type', TransactionType::Income->value)
            ->sum('amount');
        $expense = abs((float) (clone $cycleTransactions)
            ->where('transaction_type', TransactionType::Expense->value)
            ->sum('amount'));

        $flatAccounts = $ledger->accounts()
            ->visible()
            ->with('accountType')
            ->withSum('transactions', 'amount')
            ->get()
            ->map(function ($account) {
                $balance = (float) $account->initial_balance + (float) ($account->transactions_sum_amount ?? 0);
                $account->balance = round($balance, 2);

                return $account;
            });

        $accounts = $flatAccounts
            ->groupBy('account_type_id')
            ->map(fn ($accounts) => [
                'type' => $accounts->first()->accountType,
                'accounts' => $accounts->values(),
            ])
            ->values();

        $creditTypeIds = $flatAccounts->pluck('accountType')
            ->filter()
            ->where('is_credit', true)
            ->pluck('id')
            ->unique();
        $totalAssets = $flatAccounts->reject(fn ($a) => $creditTypeIds->contains($a->account_type_id))->sum('balance');
        $totalLiabilities = $flatAccounts->filter(fn ($a) => $creditTypeIds->contains($a->account_type_id))->sum('balance');

        $recentTransactions = $ledger->transactions()
            ->with(['account', 'category', 'payee'])
            ->whereBetween('transaction_date', [$start, $end])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        $categories = $ledger->categories()->with('children')->orderBy('position')->get();
        $payees = $ledger->payees()->orderBy('name')->get();
        $tags = $ledger->tags()->orderBy('name')->get();

        $dailyTotals = (clone $cycleTransactions)
            ->select(
                'transaction_date',
                'transaction_type',
                DB::raw('SUM(amount) as total'),
            )
            ->groupBy('transaction_date', 'transaction_type')
            ->get()
            ->groupBy(fn ($row) => $row->transaction_date->format('Y-m-d'));

        $trendEnd = $end->isBefore($now) ? $end : $now;
        $period = CarbonPeriod::create($start, $trendEnd);

        $dailyExpenseTrend = collect($period)->map(function ($day) use ($dailyTotals) {
            $key = $day->format('Y-m-d');
            $dayData = $dailyTotals->get($key, collect());

            $dayExpense = (float) $dayData
                ->firstWhere('transaction_type', TransactionType::Expense->value)
                ?->total ?? 0;

            $dayIncome = (float) $dayData
                ->firstWhere('transaction_type', TransactionType::Income->value)
                ?->total ?? 0;

            return [
                'date' => $key,
                'expense' => round(abs($dayExpense), 2),
                'income' => round($dayIncome, 2),
            ];
        })->values()->all();

        $topCategories = (clone $cycleTransactions)
            ->where('transaction_type', TransactionType::Expense->value)
            ->select(
                'category_id',
                DB::raw('SUM(amount) as total'),
            )
            ->groupBy('category_id')
            ->orderByRaw('SUM(amount) ASC')
            ->limit(5)
            ->get();

        $topCategoryLookup = $ledger->categories()
            ->whereIn('id', $topCategories->pluck('category_id')->filter()->all())
            ->get()
            ->keyBy('id');

        $topCategories = $topCategories
            ->map(function ($row) use ($topCategoryLookup) {
                $category = $row->category_id
                    ? $topCategoryLookup->get($row->category_id)
                    : null;

                return [
                    'name' => $category?->name ?? 'Uncategorized',
                    'color' => $category?->color,
                    'total' => round(abs((float) $row->total), 2),
                ];
            })
            ->values()
            ->all();

        $uncategorizedCount = Transaction::query()
            ->where('ledger_id', $ledger->id)
            ->whereNull('category_id')
            ->where('transaction_type', '!=', TransactionType::Transfer)
            ->count();

        return Inertia::render('ledgers/dashboard', [
            'ledger' => $ledger,
            'summary' => [
                'income' => $income,
                'expense' => $expense,
                'net' => $income - $expense,
            ],
            'accounts' => $accounts,
            'flatAccounts' => $flatAccounts->values(),
            'upcomingBills' => $billService->getUpcomingBills($ledger),
            'recentTransactions' => $recentTransactions,
            'categories' => $categories,
            'payees' => $payees,
            'tags' => $tags,
            'dailyExpenseTrend' => $dailyExpenseTrend,
            'cycleDates' => [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
            ],
            'topCategories' => $topCategories,
            'cycleOffset' => $cycleOffset,
            'topBudgets' => array_slice($budgetService->getBudgetsWithStats($ledger), 0, 3),
            'uncategorizedCount' => $uncategorizedCount,
            'netWorth' => [
                'assets' => round($totalAssets, 2),
                'liabilities' => round($totalLiabilities, 2),
                'net' => round($totalAssets + $totalLiabilities, 2),
            ],
        ]);
    }
}
