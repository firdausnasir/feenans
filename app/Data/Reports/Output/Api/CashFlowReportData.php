<?php

namespace App\Data\Reports\Output\Api;

use App\Data\Reports\Output\Web\CashFlowReportData as WebCashFlowReportData;

class CashFlowReportData extends WebCashFlowReportData
{
    public static function fromWebResult(WebCashFlowReportData $result): self
    {
        return self::from($result->toArray());
    }
}
