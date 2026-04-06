<?php

namespace App\Actions\Accounts\UseCases;

use App\Data\Accounts\Input\AdjustAccountBalanceData;
use App\Enums\TransactionType;
use Carbon\CarbonImmutable;

class AdjustAccountBalanceAction
{
    public function __invoke(AdjustAccountBalanceData $data): void
    {
        $amount = $data->amount;

        $transactionType = $amount > 0
            ? TransactionType::Income
            : TransactionType::Expense;

        $data->ledger->transactions()->create([
            'account_id' => $data->account->id,
            'transaction_type' => $transactionType,
            'amount' => $amount,
            'description' => $data->description ?? 'Balance adjustment',
            'transaction_date' => $data->date ?? CarbonImmutable::today()->toDateString(),
        ]);
    }
}
