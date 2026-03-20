<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ParseImportRequest;
use App\Http\Requests\StoreImportRequest;
use App\Models\ImportMapping;
use App\Models\Ledger;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function __construct(private readonly ImportService $importService) {}

    public function history(Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        return response()->json([
            'data' => $this->importService->historyForLedger($ledger),
        ]);
    }

    /**
     * Parse CSV and return headers + first 10 rows for column mapping.
     */
    public function parse(ParseImportRequest $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        try {
            return response()->json($this->importService->parseUploadedFile($request->file('file')));
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['file' => [$exception->getMessage()]],
            ], 422);
        }
    }

    /**
     * Import transactions from mapped CSV.
     */
    public function execute(StoreImportRequest $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        try {
            return response()->json($this->importService->executeImport($ledger, $request->validated()));
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['file_path' => [$exception->getMessage()]],
            ], 422);
        }
    }

    /**
     * Return saved import mappings for the ledger.
     */
    public function mappings(Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        return response()->json([
            'data' => $this->importService->mappingsForLedger($ledger),
        ]);
    }

    /**
     * Save a new import mapping configuration.
     */
    public function storeMapping(Request $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mapping' => ['required', 'array'],
        ]);

        $importMapping = $ledger->importMappings()->create([
            'name' => $validated['name'],
            'mapping' => $validated['mapping'],
        ]);

        return response()->json(['data' => $importMapping], 201);
    }

    /**
     * Delete a saved import mapping.
     */
    public function destroyMapping(Ledger $ledger, ImportMapping $importMapping): JsonResponse
    {
        $this->authorize('view', $ledger);

        if ($importMapping->ledger_id !== $ledger->id) {
            abort(404);
        }

        $importMapping->delete();

        return response()->json(null, 204);
    }
}
