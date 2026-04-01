<?php

namespace App\Actions\Payees\UseCases;

use App\Data\Payees\Input\UpdatePayeeData;
use App\Data\Payees\Output\PayeeData;

class UpdatePayeeAction
{
    public function __invoke(UpdatePayeeData $data): PayeeData
    {
        $data->payee->update([
            'name' => $data->name,
        ]);

        return PayeeData::fromModel($data->payee->fresh()->loadCount('transactions'));
    }
}
