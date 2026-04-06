<?php

namespace App\Data\Reports\Output\Web;

use App\Data\Shared\Output\BaseOutputData;

class ReportDateRangeData extends BaseOutputData
{
    public function __construct(
        public readonly string $date_from,
        public readonly string $date_to,
        public readonly string $preset,
        public readonly ?string $account_id,
    ) {}
}
