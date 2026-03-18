<?php

namespace App\Http\Controllers\Ledger;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Ledger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountController extends Controller
{
    public function index(Ledger $ledger, Request $request): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/accounts/index');
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
}
