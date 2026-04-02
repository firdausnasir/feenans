<?php

namespace App\Actions\Reports\Queries;

use App\Data\Reports\Input\GetFinancialHealthPageData;
use App\Data\Reports\Output\Web\FinancialHealthReportData;
use App\Enums\TransactionType;
use App\Models\Ledger;
use Carbon\CarbonImmutable;

class GetFinancialHealthReportDataQuery
{
    public function __invoke(Ledger $ledger, GetFinancialHealthPageData $input): FinancialHealthReportData
    {
        $accounts = $ledger->accounts()
            ->visible()
            ->with('accountType')
            ->withSum('transactions', 'amount')
            ->get();

        $today = CarbonImmutable::today();

        $cycleBoundaries = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = $today->subMonths($i);
            $cycleBoundaries[] = $ledger->cycleBounds($date);
        }

        $latestEnd = $cycleBoundaries[11]['end']->toDateString();

        $accountIds = $accounts->pluck('id')->toArray();

        $allTransactions = $ledger->transactions()
            ->whereIn('account_id', $accountIds)
            ->where('transaction_date', '<=', $latestEnd)
            ->selectRaw('account_id, transaction_date, SUM(amount) as total')
            ->groupBy('account_id', 'transaction_date')
            ->orderBy('transaction_date')
            ->get();

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

        return new FinancialHealthReportData(
            net_worth_history: $netWorthHistory,
            savings_rate_history: $savingsRateHistory,
            current_snapshot: [
                'assets' => round($currentAssets, 2),
                'liabilities' => round($currentLiabilities, 2),
                'net_worth' => round($currentAssets - $currentLiabilities, 2),
                'debt_to_asset_ratio' => $currentAssets > 0 ? round($currentLiabilities / $currentAssets, 2) : 0.0,
            ],
        );
    }
}
