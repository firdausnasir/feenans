<?php

namespace App\Actions\Imports\UseCases;

use App\Data\Imports\Output\Web\ImportMappingData;
use App\Models\ImportMapping;
use App\Models\Ledger;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DeleteImportMappingAction
{
    public function __invoke(Ledger $ledger, ImportMapping $importMapping): ImportMappingData
    {
        if ($importMapping->ledger_id !== $ledger->id) {
            throw new NotFoundHttpException;
        }

        $data = ImportMappingData::fromModel($importMapping);

        $importMapping->delete();

        return $data;
    }
}
