<?php

namespace App\Data\Imports\Output\Web;

use App\Data\Shared\Output\BaseOutputData;
use App\Models\ImportRecord;

class ImportHistoryData extends BaseOutputData
{
    /**
     * @param  array<string, string>|null  $mapping_used
     */
    public function __construct(
        public int $id,
        public string $filename,
        public int $row_count,
        public int $imported_count,
        public int $skipped_count,
        public ?array $mapping_used,
        public string $imported_at,
    ) {}

    public static function fromModel(ImportRecord $record): self
    {
        return new self(
            id: $record->id,
            filename: $record->filename,
            row_count: $record->row_count,
            imported_count: $record->imported_count,
            skipped_count: $record->skipped_count,
            mapping_used: $record->mapping_used,
            imported_at: $record->imported_at?->toIso8601String() ?? '',
        );
    }
}
