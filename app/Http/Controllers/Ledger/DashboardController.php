<?php

namespace App\Http\Controllers\Ledger;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Resources\BillResource;
use App\Http\Resources\TransactionResource;
use App\Models\Ledger;
use App\Services\BillService;
use App\Services\BudgetService;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        Ledger $ledger,
        Request $request,
        BillService $billService,
        BudgetService $budgetService,
    ): Response {
        $this->authorize('view', $ledger);
        $request->session()->put('current_ledger_id', $ledger->id);

        $offset = $request->integer('offset', 0);
        $cycle = $this->computeCycle($ledger, $offset);
        $dateFrom = $cycle['cycle_start'];
        $dateTo = $cycle['cycle_end'];

        return Inertia::render('ledgers/dashboard', [
            // Immediate — rendered on first paint
            'cycle' => $cycle,
            'summary' => fn () => $this->buildSummary($ledger, $dateFrom, $dateTo),
            'accounts' => fn () => $this->buildAccounts($ledger),

            // Deferred — loaded in a single follow-up request
            'dailyTrend' => Inertia::defer(
                fn () => $this->buildDailyTrend($ledger, $dateFrom, $dateTo),
            ),
            'topCategories' => Inertia::defer(
                fn () => $this->buildTopCategories($ledger, $dateFrom, $dateTo),
            ),
            'recentTransactions' => Inertia::defer(
                fn () => $this->buildRecentTransactions($ledger, $dateFrom, $dateTo),
            ),
            'uncategorizedCount' => Inertia::defer(
                fn () => $this->buildUncategorizedCount($ledger, $dateFrom, $dateTo),
            ),
            'upcomingBills' => Inertia::defer(
                fn () => $this->buildUpcomingBills($ledger, $billService),
            ),
            'topBudgets' => Inertia::defer(
                fn () => $this->buildTopBudgets($ledger, $budgetService),
            ),
        ]);
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
    private function buildSummary(Ledger $ledger, string $dateFrom, string $dateTo): array
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

        $from = CarbonImmutable::parse($dateFrom);
        $to = CarbonImmutable::parse($dateTo);
        $periodLength = $from->diffInDays($to);
        $prevFrom = $from->subDays($periodLength + 1)->toDateString();
        $prevTo = $from->subDay()->toDateString();

        $prev = $ledger->transactions()
            ->whereBetween('transaction_date', [$prevFrom, $prevTo])
            ->selectRaw('COALESCE(SUM(CASE WHEN transaction_type = ? THEN amount END), 0) as income', [$incomeType])
            ->selectRaw('COALESCE(SUM(CASE WHEN transaction_type = ? THEN amount END), 0) as expense', [$expenseType])
            ->first();

        $prevIncome = (float) $prev->income;
        $prevExpense = (float) $prev->expense;

        return [
            'income' => round($income, 2),
            'expense' => round($expense, 2),
            'net' => round($income + $expense, 2),
            'prev_income' => round($prevIncome, 2),
            'prev_expense' => round($prevExpense, 2),
        ];
    }

    /**
     * @return array<int, array{type: array, accounts: array, total_balance: string}>
     */
    private function buildAccounts(Ledger $ledger): array
    {
        $accounts = $ledger->accounts()
            ->with('accountType')
            ->visible()
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $accountTypes = $ledger->accountTypes()->orderBy('position')->get();

        return AccountController::groupAccountsByType($accounts, $accountTypes);
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
     * @return array{upcoming: array, due: array, missed: array}
     */
    private function buildUpcomingBills(Ledger $ledger, BillService $billService): array
    {
        $groups = $billService->getUpcomingBills($ledger, 3);

        return [
            'upcoming' => BillResource::collection($groups['upcoming'])->resolve(),
            'due' => BillResource::collection($groups['due'])->resolve(),
            'missed' => BillResource::collection($groups['missed'])->resolve(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildTopBudgets(Ledger $ledger, BudgetService $budgetService): array
    {
        return collect($budgetService->getBudgetsWithStats($ledger))
            ->sortByDesc('percentage')
            ->take(3)
            ->values()
            ->all();
    }
}
