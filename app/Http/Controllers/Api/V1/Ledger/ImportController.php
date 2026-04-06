<?php

namespace App\Http\Controllers\Api\V1\Ledger;

use App\Actions\Imports\Queries\GetImportPageQuery;
use App\Actions\Imports\UseCases\DeleteImportMappingAction;
use App\Actions\Imports\UseCases\ExecuteImportAction;
use App\Actions\Imports\UseCases\ParseImportAction;
use App\Actions\Imports\UseCases\StoreImportMappingAction;
use App\Data\Imports\Input\GetImportPageData;
use App\Data\Imports\Input\ParseImportData;
use App\Data\Imports\Input\StoreImportData;
use App\Data\Imports\Input\StoreImportMappingData;
use App\Data\Imports\Output\Api\ImportParseResultData as ApiImportParseResultData;
use App\Http\Controllers\Controller;
use App\Models\ImportMapping;
use App\Models\Ledger;
use Illuminate\Http\JsonResponse;

class ImportController extends Controller
{
    public function accounts(Ledger $ledger, GetImportPageData $data, GetImportPageQuery $getImportPage): JsonResponse
    {
        return response()->json([
            'data' => $getImportPage($ledger, $data)->accounts(),
        ]);
    }

    public function savedMappings(Ledger $ledger, GetImportPageData $data, GetImportPageQuery $getImportPage): JsonResponse
    {
        return response()->json([
            'data' => $getImportPage($ledger, $data)->savedMappings(),
        ]);
    }

    public function history(Ledger $ledger, GetImportPageData $data, GetImportPageQuery $getImportPage): JsonResponse
    {
        return response()->json([
            'data' => $getImportPage($ledger, $data)->importHistory(),
        ]);
    }

    public function parse(Ledger $ledger, ParseImportData $data, ParseImportAction $parseImport): JsonResponse
    {
        return response()->json([
            'data' => ApiImportParseResultData::fromWebResult($parseImport($data))->toArray(),
        ]);
    }

    public function execute(Ledger $ledger, StoreImportData $data, ExecuteImportAction $executeImport): JsonResponse
    {
        return response()->json([
            'data' => $executeImport($data)->toArray(),
        ]);
    }

    public function store(
        Ledger $ledger,
        StoreImportMappingData $data,
        StoreImportMappingAction $storeImportMapping,
    ): JsonResponse {
        return response()->json([
            'data' => $storeImportMapping($data)->toArray(),
        ], 201);
    }

    public function destroy(
        Ledger $ledger,
        ImportMapping $importMapping,
        DeleteImportMappingAction $deleteImportMapping,
    ): JsonResponse {
        $this->authorize('view', $ledger);

        return response()->json([
            'data' => $deleteImportMapping($ledger, $importMapping)->toArray(),
        ]);
    }
}
