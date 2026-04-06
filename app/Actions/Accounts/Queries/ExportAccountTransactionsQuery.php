<?php

namespace App\Actions\Accounts\Queries;

use App\Data\Accounts\Output\AccountExportRowData;
use App\Models\Account;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportAccountTransactionsQuery
{
    public function __invoke(Account $account, ?string $dateFrom, ?string $dateTo): StreamedResponse
    {
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

        return response()->streamDownload(function () use ($query, $account, $dateFrom): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, AccountExportRowData::csvHeaders());

            $runningBalance = (float) $account->initial_balance;

            if ($dateFrom) {
                $priorSum = (float) $account->transactions()
                    ->where('transaction_date', '<', $dateFrom)
                    ->sum('amount');
                $runningBalance += $priorSum;
            }

            $query->chunk(500, function ($transactions) use ($handle, &$runningBalance): void {
                foreach ($transactions as $transaction) {
                    $runningBalance += (float) $transaction->amount;
                    $row = AccountExportRowData::fromTransaction($transaction, $runningBalance);
                    fputcsv($handle, $row->toCsvRow());
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
