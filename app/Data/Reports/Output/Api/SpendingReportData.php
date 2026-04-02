<?php

namespace App\Data\Reports\Output\Api;

use App\Data\Reports\Output\Web\SpendingReportData as WebSpendingReportData;

class SpendingReportData extends WebSpendingReportData
{
    public static function fromWebResult(WebSpendingReportData $result): self
    {
        return self::from($result->toArray());
    }
}
