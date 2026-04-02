<?php

namespace App\Actions\Bills\UseCases;

use App\Models\Bill;

class ToggleBillAction
{
    public function __invoke(Bill $bill): Bill
    {
        $bill->update(['is_active' => ! $bill->is_active]);

        /** @var Bill $toggledBill */
        $toggledBill = $bill->fresh();

        return $toggledBill->loadMissing(['account', 'toAccount', 'category', 'payee']);
    }
}
