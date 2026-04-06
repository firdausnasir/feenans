<?php

namespace App\Data\Bills\Output\Api;

use App\Data\Bills\Output\Web\BillData as WebBillData;
use App\Models\Bill;

class BillData extends WebBillData
{
    public static function fromModel(Bill $bill, int $missedCycles = 0): self
    {
        return self::from(WebBillData::fromModel($bill, $missedCycles)->toArray());
    }
}
