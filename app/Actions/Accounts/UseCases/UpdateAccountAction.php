<?php

namespace App\Actions\Accounts\UseCases;

use App\Data\Accounts\Input\UpdateAccountData;
use App\Data\Accounts\Output\Web\AccountData;

class UpdateAccountAction
{
    public function __invoke(UpdateAccountData $data): AccountData
    {
        $data->account->update($data->attributesToUpdate());

        return AccountData::fromModel($data->account->fresh()->load('accountType'));
    }
}
