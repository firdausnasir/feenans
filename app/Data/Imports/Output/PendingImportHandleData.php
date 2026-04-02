<?php

namespace App\Data\Imports\Output;

use App\Data\Shared\Output\BaseOutputData;

class PendingImportHandleData extends BaseOutputData
{
    public function __construct(
        public string $disk,
        public string $file_path,
        public ?string $pending_import_handle = null,
    ) {}
}
