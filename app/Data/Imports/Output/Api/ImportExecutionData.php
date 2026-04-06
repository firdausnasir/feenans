<?php

namespace App\Data\Imports\Output\Api;

use App\Data\Shared\Output\BaseOutputData;

class ImportExecutionData extends BaseOutputData
{
    public function __construct(
        public int $row_count,
        public int $imported_count,
        public int $skipped_count,
        public string $message,
    ) {}
}
