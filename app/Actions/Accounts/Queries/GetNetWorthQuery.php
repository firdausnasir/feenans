<?php

namespace App\Actions\Accounts\Queries;

use App\Data\Accounts\Output\Web\AccountNetWorthData;
use App\Models\Ledger;

class GetNetWorthQuery
{
    public function __invoke(Ledger $ledger): AccountNetWorthData
    {
        $flatAccounts = $ledger->accounts()
            ->visible()
            ->with('accountType')
            ->withCurrentBalance()
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

        return new AccountNetWorthData(
            assets: round($totalAssets, 2),
            liabilities: round($totalLiabilities, 2),
            net: round($totalAssets + $totalLiabilities, 2),
            trend: $trend,
        );
    }
}
