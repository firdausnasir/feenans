<?php

namespace App\Actions\Accounts\UseCases;

use App\Data\Accounts\Output\Web\AccountData;
use App\Models\Account;

class DeleteAccountAction
{
    public function __invoke(Account $account): AccountData
    {
        $data = AccountData::fromModel($account->load('accountType'));

        $account->delete();

        return $data;
    }
}
