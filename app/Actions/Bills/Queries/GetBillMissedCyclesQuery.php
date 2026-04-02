<?php

namespace App\Actions\Bills\Queries;

use App\Models\Bill;
use Carbon\CarbonImmutable;

class GetBillMissedCyclesQuery
{
    public function __invoke(Bill $bill): int
    {
        $today = CarbonImmutable::today();

        if ($bill->next_due_date->gte($today)) {
            return 0;
        }

        $current = $bill->next_due_date;
        $count = 0;

        while ($current->lt($today)) {
            $current = $bill->nextDueDateAfter($current);
            $count++;
        }

        return $count;
    }
}
