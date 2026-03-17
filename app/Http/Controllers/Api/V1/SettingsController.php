<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Ledger;
use App\Services\SampleDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(private readonly SampleDataService $sampleDataService) {}

    public function index(Request $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        $apiTokens = $request->user()->tokens()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
            ]);

        $ledger->load(['accountTypes' => fn ($q) => $q->orderBy('position')]);

        return response()->json([
            'ledger' => $ledger,
            'account_types' => $ledger->accountTypes,
            'has_sample_data' => $this->sampleDataService->hasSampleData($ledger),
            'api_tokens' => $apiTokens,
        ]);
    }

    public function update(UpdateSettingsRequest $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('update', $ledger);

        $ledger->update($request->validated());

        return response()->json([
            'data' => $ledger->fresh(),
        ]);
    }

    public function generateSampleData(Ledger $ledger): JsonResponse
    {
        $this->authorize('update', $ledger);

        if ($this->sampleDataService->hasSampleData($ledger)) {
            return response()->json([
                'message' => 'Sample data already exists.',
            ], 409);
        }

        $this->sampleDataService->generate($ledger);

        return response()->json([
            'message' => 'Sample data generated successfully.',
        ], 201);
    }

    public function removeSampleData(Ledger $ledger): JsonResponse
    {
        $this->authorize('update', $ledger);

        $this->sampleDataService->remove($ledger);

        return response()->json([
            'message' => 'Sample data removed successfully.',
        ]);
    }
}
