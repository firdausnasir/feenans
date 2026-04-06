<?php

namespace App\Actions\Payees\UseCases;

use App\Data\Payees\Input\StorePayeeData;
use App\Data\Payees\Output\PayeeData;

class StorePayeeAction
{
    public function __invoke(StorePayeeData $data): PayeeData
    {
        $payee = $data->ledger->payees()->create([
            'name' => $data->name,
        ]);

        return PayeeData::fromModel($payee->loadCount('transactions'));
    }
}
