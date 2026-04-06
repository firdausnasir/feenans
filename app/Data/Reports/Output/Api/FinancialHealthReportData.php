<?php

namespace App\Data\Reports\Output\Api;

use App\Data\Reports\Output\Web\FinancialHealthReportData as WebFinancialHealthReportData;

class FinancialHealthReportData extends WebFinancialHealthReportData
{
    public static function fromWebResult(WebFinancialHealthReportData $result): self
    {
        return self::from($result->toArray());
    }
}
