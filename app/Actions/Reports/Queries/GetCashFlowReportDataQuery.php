<?php

namespace App\Actions\Reports\Queries;

use App\Data\Reports\Input\GetCashFlowPageData;
use App\Data\Reports\Output\Web\CashFlowReportData;
use App\Enums\TransactionType;
use App\Models\Ledger;
use Carbon\CarbonImmutable;

class GetCashFlowReportDataQuery
{
    public function __invoke(Ledger $ledger, GetCashFlowPageData $input): CashFlowReportData
    {
        $today = CarbonImmutable::today();
        $currentCycle = $ledger->cycleBounds($today);

        $dateFrom = $input->date_from ?? $currentCycle['start']->toDateString();
        $dateTo = $input->date_to ?? $currentCycle['end']->toDateString();
        $accountId = $input->account_id;

        $parsedFrom = CarbonImmutable::parse($dateFrom)->startOfDay();
        $parsedTo = CarbonImmutable::parse($dateTo)->endOfDay();

        $dailyFlow = $ledger->transactions()
            ->whereIn('transaction_type', [TransactionType::Income->value, TransactionType::Expense->value])
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
            ->selectRaw('transaction_date, transaction_type, SUM(amount) as total')
            ->groupBy('transaction_date', 'transaction_type')
            ->orderBy('transaction_date')
            ->get();

        $dailyFlowByDate = [];

        foreach ($dailyFlow as $row) {
            $dateStr = $row->transaction_date->toDateString();
            $dailyFlowByDate[$dateStr] ??= ['income' => 0.0, 'expense' => 0.0];

            if ($row->transaction_type === TransactionType::Income) {
                $dailyFlowByDate[$dateStr]['income'] += (float) $row->total;
            } else {
                $dailyFlowByDate[$dateStr]['expense'] += abs((float) $row->total);
            }
        }

        $dailyCashFlow = [];
        $cursor = $parsedFrom;
        $cumulative = 0.0;

        while ($cursor->lte($parsedTo)) {
            $dateStr = $cursor->toDateString();
            $dayData = $dailyFlowByDate[$dateStr] ?? ['income' => 0.0, 'expense' => 0.0];

            $income = round($dayData['income'], 2);
            $expense = round($dayData['expense'], 2);
            $net = round($income - $expense, 2);
            $cumulative = round($cumulative + $net, 2);

            $dailyCashFlow[] = [
                'date' => $dateStr,
                'income' => $income,
                'expense' => $expense,
                'net' => $net,
                'cumulative' => $cumulative,
            ];

            $cursor = $cursor->addDay();
        }

        $upcomingBills = $ledger->bills()
            ->where('is_active', true)
            ->where('next_due_date', '>=', $today->toDateString())
            ->where('next_due_date', '<=', $today->addMonths(3)->toDateString())
            ->with(['account', 'category', 'payee'])
            ->orderBy('next_due_date')
            ->get()
            ->map(fn ($bill) => [
                'id' => $bill->id,
                'name' => $bill->name,
                'amount' => round((float) $bill->amount, 2),
                'transaction_type' => $bill->transaction_type,
                'next_due_date' => $bill->next_due_date->toDateString(),
                'account_name' => $bill->account?->name,
            ])
            ->values()
            ->toArray();

        return new CashFlowReportData(
            daily_cash_flow: $dailyCashFlow,
            upcoming_bills: $upcomingBills,
            period_label: $parsedFrom->format('M d').' – '.$parsedTo->format('M d, Y'),
        );
    }
}
