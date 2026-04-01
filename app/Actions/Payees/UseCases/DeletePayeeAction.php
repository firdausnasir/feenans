<?php

namespace App\Actions\Payees\UseCases;

use App\Data\Payees\Output\PayeeData;
use App\Models\Payee;

class DeletePayeeAction
{
    public function __invoke(Payee $payee): PayeeData
    {
        $payeeData = PayeeData::fromModel($payee->loadCount('transactions'));

        $payee->delete();

        return $payeeData;
    }
}
