<?php

namespace App\Data\Imports\Output\Web;

use App\Data\Shared\Output\BaseOutputData;
use App\Models\ImportMapping;

class ImportMappingData extends BaseOutputData
{
    /**
     * @param  array<string, string>  $mapping
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $mapping,
    ) {}

    public static function fromModel(ImportMapping $mapping): self
    {
        return new self(
            id: $mapping->id,
            name: $mapping->name,
            mapping: $mapping->mapping ?? [],
        );
    }
}
