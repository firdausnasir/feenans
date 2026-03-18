<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdjustBalanceRequest;
use App\Http\Requests\ReorderRequest;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\TransactionResource;
use App\Models\Account;
use App\Models\Ledger;
use App\Services\TransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountController extends Controller
{
    public function index(Ledger $ledger, Request $request, TransactionService $txService): AnonymousResourceCollection|JsonResponse
    {
        $this->authorize('view', $ledger);

        $showHidden = $request->boolean('show_hidden');
        $grouped = $request->boolean('grouped');
        $withTypeTotals = $request->boolean('with_type_totals');
        $withStatement = $request->boolean('with_statement');

        $accountsQuery = $ledger->accounts()
            ->with('accountType')
            ->orderBy('position')
            ->orderBy('name');

        if (! $showHidden) {
            $accountsQuery->visible();
        }

        $accounts = $accountsQuery->get();

        if ($grouped && $withStatement) {
            $accounts = $this->attachStatementData($accounts, $txService);
        }

        if (! $grouped) {
            return AccountResource::collection($accounts);
        }

        $accountTypes = $ledger->accountTypes()->orderBy('position')->get();

        $groupedData = $accountTypes
            ->map(function ($type) use ($accounts, $withTypeTotals) {
                $typeAccounts = $accounts->where('account_type_id', $type->id)->values();

                if ($typeAccounts->isEmpty()) {
                    return null;
                }

                $group = [
                    'type' => [
                        'id' => $type->id,
                        'name' => $type->name,
                        'color' => $type->color,
                        'is_credit' => $type->is_credit,
                    ],
                    'accounts' => AccountResource::collection($typeAccounts),
                ];

                if ($withTypeTotals) {
                    $group['total_balance'] = number_format(
                        $typeAccounts->sum(fn ($a) => (float) $a->current_balance),
                        2,
                        '.',
                        '',
                    );
                }

                return $group;
            })
            ->filter()
            ->values();

        return response()->json(['data' => $groupedData]);
    }

    /**
     * Attach statement cycle data to accounts that have a statement_day set.
     *
     * @param  Collection<int, Account>  $accounts
     * @return Collection<int, Account>
     */
    private function attachStatementData(Collection $accounts, TransactionService $txService): Collection
    {
        $today = CarbonImmutable::today();

        return $accounts->map(function (Account $account) use ($txService, $today): Account {
            if ($account->statement_day === null) {
                return $account;
            }

            [$currentStart, $currentEnd] = $txService->statementCycleBounds($account, $today);

            $previousEnd = $currentStart->subDay();
            [$previousStart, $calculatedPreviousEnd] = $txService->statementCycleBounds($account, $previousEnd);

            $statementBalance = (float) $account->transactions()
                ->whereBetween('transaction_date', [$previousStart->toDateString(), $calculatedPreviousEnd->toDateString()])
                ->sum('amount');

            $currentSpending = (float) $account->transactions()
                ->whereBetween('transaction_date', [$currentStart->toDateString(), $today->toDateString()])
                ->sum('amount');

            if ($account->payment_due_day !== null) {
                $stmtDate = $currentStart->subDay();
                $dueMonth = $account->payment_due_day >= $account->statement_day
                    ? $stmtDate
                    : $stmtDate->addMonthNoOverflow();
                $paymentDueDate = $dueMonth->setDay(min($account->payment_due_day, $dueMonth->daysInMonth));
            } else {
                $paymentDueDate = $currentStart->subDay()->addDays(20);
            }

            $account->setAttribute('statement_start', $previousStart->toDateString());
            $account->setAttribute('statement_end', $calculatedPreviousEnd->toDateString());
            $account->setAttribute('statement_balance', round(abs($statementBalance), 2));
            $account->setAttribute('current_spending', round(abs($currentSpending), 2));
            $account->setAttribute('outstanding', round(abs($statementBalance) + abs($currentSpending), 2));
            $account->setAttribute('payment_due_date', $paymentDueDate->toDateString());

            return $account;
        });
    }

    public function show(Ledger $ledger, Account $account): AccountResource
    {
        $this->authorize('view', $ledger);

        return new AccountResource($account->load('accountType'));
    }

    public function store(StoreAccountRequest $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        $account = $ledger->accounts()->create($request->validated());

        return (new AccountResource($account->load('accountType')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateAccountRequest $request, Ledger $ledger, Account $account): AccountResource
    {
        $this->authorize('update', $ledger);

        $account->update($request->validated());

        return new AccountResource($account->fresh('accountType'));
    }

    public function destroy(Ledger $ledger, Account $account): JsonResponse
    {
        $this->authorize('delete', $ledger);

        $account->delete();

        return response()->json(null, 204);
    }

    public function transactions(Ledger $ledger, Account $account, Request $request): AnonymousResourceCollection
    {
        $this->authorize('view', $ledger);

        $perPage = (int) $request->query('per_page', 20);
        $perPage = min(max($perPage, 1), 100);

        $transactions = $account->transactions()
            ->with(['category', 'payee'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        return TransactionResource::collection($transactions);
    }

    public function statement(Ledger $ledger, Account $account, TransactionService $txService): JsonResponse
    {
        $this->authorize('view', $ledger);

        if ($account->statement_day === null) {
            return response()->json([
                'data' => [
                    'statement_start' => null,
                    'statement_end' => null,
                    'statement_balance' => null,
                    'current_start' => null,
                    'current_end' => null,
                    'current_spending' => null,
                    'outstanding' => null,
                    'payment_due_date' => null,
                ],
            ]);
        }

        $today = CarbonImmutable::today();

        [$currentStart, $currentEnd] = $txService->statementCycleBounds($account, $today);

        $previousEnd = $currentStart->subDay();
        [$previousStart, $calculatedPreviousEnd] = $txService->statementCycleBounds($account, $previousEnd);

        $statementBalance = (float) $account->transactions()
            ->whereBetween('transaction_date', [$previousStart->toDateString(), $calculatedPreviousEnd->toDateString()])
            ->sum('amount');

        $currentSpending = (float) $account->transactions()
            ->whereBetween('transaction_date', [$currentStart->toDateString(), $today->toDateString()])
            ->sum('amount');

        if ($account->payment_due_day !== null) {
            $stmtDate = $currentStart->subDay();
            $dueMonth = $account->payment_due_day >= $account->statement_day
                ? $stmtDate
                : $stmtDate->addMonthNoOverflow();
            $paymentDueDate = $dueMonth->setDay(min($account->payment_due_day, $dueMonth->daysInMonth));
        } else {
            $paymentDueDate = $currentStart->subDay()->addDays(20);
        }

        return response()->json([
            'data' => [
                'statement_start' => $previousStart->toDateString(),
                'statement_end' => $calculatedPreviousEnd->toDateString(),
                'statement_balance' => round(abs($statementBalance), 2),
                'current_start' => $currentStart->toDateString(),
                'current_end' => $currentEnd->toDateString(),
                'current_spending' => round(abs($currentSpending), 2),
                'outstanding' => round(abs($statementBalance) + abs($currentSpending), 2),
                'payment_due_date' => $paymentDueDate->toDateString(),
            ],
        ]);
    }

    public function monthlyBalances(Ledger $ledger, Account $account, Request $request): JsonResponse
    {
        $this->authorize('view', $ledger);

        $months = (int) $request->query('months', 6);
        $months = min(max($months, 1), 24);

        $now = CarbonImmutable::today();
        $startDate = $now->subMonths($months - 1)->startOfMonth();

        $priorSum = (float) $account->transactions()
            ->where('transaction_date', '<', $startDate->toDateString())
            ->sum('amount');

        $monthlySums = $account->transactions()
            ->where('transaction_date', '>=', $startDate->toDateString())
            ->selectRaw("strftime('%Y-%m', transaction_date) as month, SUM(amount) as total")
            ->groupBy(DB::raw("strftime('%Y-%m', transaction_date)"))
            ->orderBy('month')
            ->pluck('total', 'month');

        $runningBalance = (float) $account->initial_balance + $priorSum;
        $result = [];

        for ($i = 0; $i < $months; $i++) {
            $monthDate = $startDate->addMonths($i);
            $monthKey = $monthDate->format('Y-m');
            $monthLabel = $monthDate->format('M y');

            $monthTotal = (float) ($monthlySums[$monthKey] ?? 0);
            $runningBalance += $monthTotal;

            $result[] = [
                'month' => $monthLabel,
                'balance' => round($runningBalance, 2),
            ];
        }

        return response()->json(['data' => $result]);
    }

    public function netWorth(Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

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

        $creditTypeIds = $flatAccounts->pluck('accountType')
            ->filter()
            ->where('is_credit', true)
            ->pluck('id')
            ->unique();

        $totalAssets = $flatAccounts->reject(fn ($a) => $creditTypeIds->contains($a->account_type_id))->sum('balance');
        $totalLiabilities = $flatAccounts->filter(fn ($a) => $creditTypeIds->contains($a->account_type_id))->sum('balance');

        $totalInitial = $flatAccounts->sum(fn ($a) => (float) $a->initial_balance);
        $accountIds = $flatAccounts->pluck('id');
        $cutoffs = collect(range(5, 0))->map(fn ($m) => now()->subMonths($m)->endOfMonth());

        $priorSum = (float) $ledger->transactions()
            ->where('transaction_date', '<', $cutoffs->first()->copy()->startOfMonth())
            ->whereIn('account_id', $accountIds)
            ->sum('amount');

        $periodTxns = $ledger->transactions()
            ->whereBetween('transaction_date', [$cutoffs->first()->copy()->startOfMonth(), $cutoffs->last()])
            ->whereIn('account_id', $accountIds)
            ->select('transaction_date', 'amount')
            ->get()
            ->groupBy(fn ($t) => $t->transaction_date->format('Y-m'));

        $running = $priorSum;
        $trend = $cutoffs->map(function ($cutoff) use ($periodTxns, &$running, $totalInitial) {
            $key = $cutoff->format('Y-m');
            $running += (float) ($periodTxns[$key] ?? collect())->sum('amount');

            return [
                'month' => $cutoff->format('M'),
                'net' => round($totalInitial + $running, 2),
            ];
        })->values()->all();

        return response()->json([
            'data' => [
                'assets' => round($totalAssets, 2),
                'liabilities' => round($totalLiabilities, 2),
                'net' => round($totalAssets + $totalLiabilities, 2),
                'trend' => $trend,
            ],
        ]);
    }

    public function toggleVisibility(Ledger $ledger, Account $account): JsonResponse
    {
        $this->authorize('update', $ledger);

        $account->update(['is_hidden' => ! $account->is_hidden]);

        return response()->json([
            'data' => new AccountResource($account->fresh('accountType')),
        ]);
    }

    public function adjustBalance(AdjustBalanceRequest $request, Ledger $ledger, Account $account): JsonResponse
    {
        $this->authorize('update', $ledger);

        $validated = $request->validated();
        $amount = (float) $validated['amount'];

        if ($amount == 0) {
            return response()->json([
                'data' => new AccountResource($account->load('accountType')),
            ]);
        }

        $transactionType = $amount > 0 ? TransactionType::Income : TransactionType::Expense;

        $ledger->transactions()->create([
            'account_id' => $account->id,
            'transaction_type' => $transactionType,
            'amount' => $amount,
            'description' => $validated['description'] ?? 'Balance adjustment',
            'transaction_date' => $validated['date'] ?? CarbonImmutable::today()->toDateString(),
        ]);

        return response()->json([
            'data' => new AccountResource($account->fresh('accountType')),
        ]);
    }

    public function reorder(ReorderRequest $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('update', $ledger);

        foreach ($request->items as $item) {
            $ledger->accounts()->where('id', $item['id'])->update(['position' => $item['position']]);
        }

        return response()->json(null, 204);
    }

    public function export(Ledger $ledger, Account $account, Request $request): StreamedResponse
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
}
