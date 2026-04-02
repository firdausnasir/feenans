<?php

namespace App\Actions\Bills\UseCases;

use App\Models\Bill;

class DeleteBillAction
{
    public function __invoke(Bill $bill): Bill
    {
        $bill->loadMissing(['account', 'toAccount', 'category', 'payee']);
        $deletedBill = clone $bill;

        $bill->delete();

        return $deletedBill;
    }
}
