<?php

namespace App\Http\Controllers\Ledger;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdjustBalanceRequest;
use App\Http\Requests\ReorderRequest;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Models\Ledger;
use App\Services\TransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountController extends Controller
{
    public function index(Ledger $ledger, Request $request, TransactionService $txService): Response
    {
        $this->authorize('view', $ledger);

        $accountTypes = $ledger->accountTypes()->orderBy('position')->get();

        return Inertia::render('ledgers/accounts/index', [
            'accounts' => fn () => $this->buildGroupedAccounts($ledger, $txService, $accountTypes),
            'accountTypes' => fn () => $accountTypes,
            'netWorth' => Inertia::defer(fn () => $this->buildNetWorth($ledger)),
        ]);
    }

    /**
     * @return array<int, array{type: array, accounts: array, total_balance: string}>
     */
    private function buildGroupedAccounts(Ledger $ledger, TransactionService $txService, $accountTypes): array
    {
        $accounts = $ledger->accounts()
            ->with('accountType')
            ->visible()
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $today = CarbonImmutable::today();
        $accounts = $accounts->map(function (Account $account) use ($txService, $today): Account {
            if ($account->statement_day === null) {
                return $account;
            }

            [$currentStart] = $txService->statementCycleBounds($account, $today);
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

        return self::groupAccountsByType($accounts, $accountTypes);
    }

    /**
     * Group accounts by their account type and format for API response.
     *
     * @return array<int, array{type: array, accounts: array, total_balance: string}>
     */
    public static function groupAccountsByType($accounts, $accountTypes): array
    {
        return $accountTypes
            ->map(function ($type) use ($accounts) {
                $typeAccounts = $accounts->where('account_type_id', $type->id)->values();

                if ($typeAccounts->isEmpty()) {
                    return null;
                }

                return [
                    'type' => [
                        'id' => $type->id,
                        'name' => $type->name,
                        'color' => $type->color,
                        'is_credit' => $type->is_credit,
                    ],
                    'accounts' => AccountResource::collection($typeAccounts)->resolve(),
                    'total_balance' => number_format(
                        $typeAccounts->sum(fn ($a) => (float) $a->current_balance),
                        2,
                        '.',
                        '',
                    ),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{assets: float, liabilities: float, net: float, trend: array}
     */
    private function buildNetWorth(Ledger $ledger): array
    {
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

        return [
            'assets' => round($totalAssets, 2),
            'liabilities' => round($totalLiabilities, 2),
            'net' => round($totalAssets + $totalLiabilities, 2),
            'trend' => $trend,
        ];
    }

    public function store(StoreAccountRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('view', $ledger);

        $ledger->accounts()->create($request->validated());

        return back()->with('success', 'Account created.');
    }

    public function update(UpdateAccountRequest $request, Ledger $ledger, Account $account): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $account->update($request->validated());

        return back()->with('success', 'Account updated.');
    }

    public function destroy(Ledger $ledger, Account $account): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $account->delete();

        return back()->with('success', 'Account deleted.');
    }

    public function reorder(ReorderRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('update', $ledger);

        foreach ($request->items as $item) {
            $ledger->accounts()->where('id', $item['id'])->update(['position' => $item['position']]);
        }

        return back();
    }

    public function adjustBalance(AdjustBalanceRequest $request, Ledger $ledger, Account $account): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $validated = $request->validated();
        $amount = (float) $validated['amount'];

        if ($amount != 0) {
            $transactionType = $amount > 0
                ? TransactionType::Income
                : TransactionType::Expense;

            $ledger->transactions()->create([
                'account_id' => $account->id,
                'transaction_type' => $transactionType,
                'amount' => $amount,
                'description' => $validated['description'] ?? 'Balance adjustment',
                'transaction_date' => $validated['date'] ?? CarbonImmutable::today()->toDateString(),
            ]);
        }

        return back()->with('success', 'Balance adjusted.');
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
