<?php

namespace App\Http\Controllers\Ledger;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderRequest;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Models\Ledger;
use App\Services\TransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountController extends Controller
{
    public function index(Ledger $ledger, Request $request): Response
    {
        $this->authorize('view', $ledger);

        $showHidden = $request->boolean('show_hidden');

        $accountsQuery = $ledger->accounts()
            ->with('accountType')
            ->withSum('transactions', 'amount')
            ->orderBy('position')
            ->orderBy('name');

        if (! $showHidden) {
            $accountsQuery->visible();
        }

        $accounts = $accountsQuery
            ->get()
            ->map(function ($account) {
                $account->current_balance = round(
                    (float) $account->initial_balance + (float) ($account->transactions_sum_amount ?? 0),
                    2,
                );

                return $account;
            });

        $accountTypes = $ledger->accountTypes()->orderBy('position')->get();

        // Compute net worth summary (only from visible, include_in_totals accounts)
        $creditTypeIds = $accountTypes->where('is_credit', true)->pluck('id');
        $totalAssets = $accounts->reject(fn ($a) => $creditTypeIds->contains($a->account_type_id))->sum('current_balance');
        $totalLiabilities = $accounts->filter(fn ($a) => $creditTypeIds->contains($a->account_type_id))->sum('current_balance');

        return Inertia::render('ledgers/accounts/index', [
            'ledger' => $ledger,
            'accounts' => $accounts,
            'accountTypes' => $accountTypes,
            'netWorth' => [
                'assets' => round($totalAssets, 2),
                'liabilities' => round($totalLiabilities, 2),
                'net' => round($totalAssets + $totalLiabilities, 2),
            ],
            'showHidden' => $showHidden,
        ]);
    }

    public function create(Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/accounts/create', [
            'ledger' => $ledger,
            'accountTypes' => $ledger->accountTypes()->orderBy('position')->get(),
        ]);
    }

    public function store(StoreAccountRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('view', $ledger);

        $ledger->accounts()->create($request->validated());

        return to_route('ledgers.accounts.index', $ledger);
    }

    public function show(Request $request, Ledger $ledger, Account $account): Response
    {
        $this->authorize('view', $ledger);

        $transactions = $account->transactions()
            ->with(['category', 'payee'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(20);

        $balance = (float) $account->initial_balance + (float) $account->transactions()->sum('amount');

        // Pre-compute monthly balance trend (last 6 months) using all transactions, not just paginated ones
        $monthlyBalances = $this->computeMonthlyBalances($account);

        // Statement period info for credit accounts
        $statementInfo = null;

        if ($account->statement_day !== null) {
            [$stmtStart, $stmtEnd] = app(TransactionService::class)
                ->statementCycleBounds($account, CarbonImmutable::today());

            $statementBalance = (float) $account->transactions()
                ->whereBetween('transaction_date', [$stmtStart->toDateString(), $stmtEnd->toDateString()])
                ->sum('amount');

            $statementInfo = [
                'start' => $stmtStart->toDateString(),
                'end' => $stmtEnd->toDateString(),
                'outstanding' => round(abs($statementBalance), 2),
            ];
        }

        return Inertia::render('ledgers/accounts/show', [
            'ledger' => $ledger,
            'account' => $account->load('accountType'),
            'transactions' => $transactions,
            'balance' => $balance,
            'monthlyBalances' => $monthlyBalances,
            'statementInfo' => $statementInfo,
        ]);
    }

    public function export(Request $request, Ledger $ledger, Account $account): StreamedResponse
    {
        $this->authorize('view', $ledger);

        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query = $account->transactions()
            ->with(['category', 'payee'])
            ->orderBy('transaction_date')
            ->orderBy('id');

        if ($dateFrom) {
            $query->where('transaction_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('transaction_date', '<=', $dateTo);
        }

        $filename = 'account-'.$account->name.'-transactions.csv';

        return response()->streamDownload(function () use ($query, $account, $dateFrom) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Date', 'Description', 'Type', 'Category', 'Payee', 'Amount', 'Running Balance', 'Notes']);

            // Calculate running balance: start from initial_balance + sum of all transactions before date range
            $runningBalance = (float) $account->initial_balance;

            if ($dateFrom) {
                $priorSum = (float) $account->transactions()
                    ->where('transaction_date', '<', $dateFrom)
                    ->sum('amount');
                $runningBalance += $priorSum;
            }

            $query->chunk(500, function ($transactions) use ($handle, &$runningBalance) {
                foreach ($transactions as $t) {
                    $amount = (float) $t->amount;
                    $runningBalance += $amount;

                    fputcsv($handle, [
                        $t->transaction_date->toDateString(),
                        $t->description ?? '',
                        $t->transaction_type instanceof TransactionType ? $t->transaction_type->value : $t->transaction_type,
                        $t->category?->name ?? '',
                        $t->payee?->name ?? '',
                        number_format($amount, 2, '.', ''),
                        number_format($runningBalance, 2, '.', ''),
                        $t->notes ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Compute monthly end-of-month balances for the last 6 months.
     *
     * Uses a single aggregate query grouped by month for efficiency,
     * then accumulates a running balance from initial_balance forward.
     *
     * @return array<int, array{month: string, balance: float}>
     */
    private function computeMonthlyBalances(Account $account): array
    {
        $now = CarbonImmutable::today();
        $sixMonthsAgo = $now->subMonths(5)->startOfMonth();

        // Sum of all transactions before the 6-month window
        $priorSum = (float) $account->transactions()
            ->where('transaction_date', '<', $sixMonthsAgo->toDateString())
            ->sum('amount');

        // Monthly sums within the 6-month window
        $monthlySums = $account->transactions()
            ->where('transaction_date', '>=', $sixMonthsAgo->toDateString())
            ->selectRaw("strftime('%Y-%m', transaction_date) as month, SUM(amount) as total")
            ->groupBy(DB::raw("strftime('%Y-%m', transaction_date)"))
            ->orderBy('month')
            ->pluck('total', 'month');

        $runningBalance = (float) $account->initial_balance + $priorSum;
        $result = [];

        for ($i = 0; $i < 6; $i++) {
            $monthDate = $sixMonthsAgo->addMonths($i);
            $monthKey = $monthDate->format('Y-m');
            $monthLabel = $monthDate->format('M y');

            $monthTotal = (float) ($monthlySums[$monthKey] ?? 0);
            $runningBalance += $monthTotal;

            $result[] = [
                'month' => $monthLabel,
                'balance' => round($runningBalance, 2),
            ];
        }

        return $result;
    }

    public function edit(Request $request, Ledger $ledger, Account $account): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/accounts/edit', [
            'ledger' => $ledger,
            'account' => $account->load('accountType'),
            'accountTypes' => $ledger->accountTypes()->orderBy('position')->get(),
        ]);
    }

    public function update(UpdateAccountRequest $request, Ledger $ledger, Account $account): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $account->update($request->validated());

        return to_route('ledgers.accounts.show', [$ledger, $account]);
    }

    public function destroy(Request $request, Ledger $ledger, Account $account): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $account->delete();

        return to_route('ledgers.accounts.index', $ledger);
    }

    public function toggleVisibility(Request $request, Ledger $ledger, Account $account): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $account->update(['is_hidden' => ! $account->is_hidden]);

        return back();
    }

    public function reorder(ReorderRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('update', $ledger);

        foreach ($request->items as $item) {
            $ledger->accounts()->where('id', $item['id'])->update(['position' => $item['position']]);
        }

        return back();
    }

    public function trash(Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/accounts/trash/index', [
            'ledger' => $ledger,
            'accounts' => $ledger->accounts()
                ->onlyTrashed()
                ->with('accountType')
                ->orderByDesc('deleted_at')
                ->get(),
        ]);
    }

    public function restore(Request $request, Ledger $ledger, Account $account): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $account->restore();

        return to_route('ledgers.accounts.trash', $ledger);
    }

    public function forceDestroy(Request $request, Ledger $ledger, Account $account): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $account->forceDelete();

        return to_route('ledgers.accounts.trash', $ledger);
    }
}
