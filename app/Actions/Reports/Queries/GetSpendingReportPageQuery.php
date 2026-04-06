<?php

namespace App\Actions\Reports\Queries;

use App\Data\Reports\Input\ReportFiltersData;
use App\Data\Reports\Output\Web\ReportDateRangeData;
use App\Models\Ledger;

class GetSpendingReportPageQuery
{
    public function __construct(
        private readonly ResolveReportDateRangeQuery $resolveReportDateRange,
    ) {}

    /**
     * @return array{dateRange: ReportDateRangeData}
     */
    public function __invoke(Ledger $ledger, ReportFiltersData $input): array
    {
        return [
            'dateRange' => ($this->resolveReportDateRange)($ledger, $input),
        ];
    }
}
