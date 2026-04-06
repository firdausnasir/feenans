<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Services\SampleDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()
                ->ledgers()
                ->orderBy('name')
                ->get()
                ->map(fn (Ledger $ledger) => [
                    'id' => $ledger->id,
                    'name' => $ledger->name,
                    'currency_code' => $ledger->currency_code,
                ])
                ->values()
                ->all(),
        ]);
    }

    public function show(Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        return response()->json([
            'data' => [
                'id' => $ledger->id,
                'name' => $ledger->name,
                'currency_code' => $ledger->currency_code,
                'cycle_start_day' => $ledger->cycle_start_day,
                'uses_seeded_categories' => $ledger->uses_seeded_categories,
            ],
        ]);
    }

    public function hasSampleData(Ledger $ledger, SampleDataService $sampleDataService): JsonResponse
    {
        $this->authorize('view', $ledger);

        return response()->json([
            'data' => $sampleDataService->hasSampleData($ledger),
        ]);
    }
}
