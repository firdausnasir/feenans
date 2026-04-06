<?php

namespace App\Data\Imports\Output\Web;

use Closure;

class ImportPageData
{
    public function __construct(
        public readonly ?ImportParseResultData $parseResult,
        private readonly Closure $accountsFactory,
        private readonly Closure $savedMappingsFactory,
        private readonly Closure $importHistoryFactory,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function accounts(): array
    {
        return ($this->accountsFactory)();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function savedMappings(): array
    {
        return ($this->savedMappingsFactory)();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function importHistory(): array
    {
        return ($this->importHistoryFactory)();
    }
}
