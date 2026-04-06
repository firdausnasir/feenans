<?php

namespace App\Data\Reports\Output\Api;

use App\Data\Reports\Output\Web\BudgetPerformanceReportData as WebBudgetPerformanceReportData;

class BudgetPerformanceReportData extends WebBudgetPerformanceReportData
{
    public static function fromWebResult(WebBudgetPerformanceReportData $result): self
    {
        return self::from($result->toArray());
    }
}
