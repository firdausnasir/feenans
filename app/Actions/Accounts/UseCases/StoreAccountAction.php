<?php

namespace App\Actions\Accounts\UseCases;

use App\Data\Accounts\Input\StoreAccountData;
use App\Data\Accounts\Output\Web\AccountData;

class StoreAccountAction
{
    public function __invoke(StoreAccountData $data): AccountData
    {
        $account = $data->ledger->accounts()->create([
            'account_type_id' => $data->account_type_id,
            'name' => $data->name,
            'color' => $data->color,
            'initial_balance' => $data->initial_balance,
            'statement_day' => $data->statement_day,
            'payment_due_day' => $data->payment_due_day,
            'include_in_totals' => $data->include_in_totals,
        ]);

        return AccountData::fromModel($account->load('accountType'));
    }
}
