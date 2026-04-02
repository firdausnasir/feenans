<?php

namespace App\Actions\Imports\UseCases;

use App\Data\Imports\Input\StoreImportMappingData;
use App\Data\Imports\Output\Web\ImportMappingData;

class StoreImportMappingAction
{
    public function __invoke(StoreImportMappingData $data): ImportMappingData
    {
        return ImportMappingData::fromModel(
            $data->ledger->importMappings()->create([
                'name' => $data->name,
                'mapping' => $data->mapping,
            ])
        );
    }
}
