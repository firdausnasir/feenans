<?php

namespace App\Http\Controllers\Api\V1\Ledger;

use App\Actions\Budgets\Queries\GetBudgetDataQuery;
use App\Actions\Budgets\Queries\ListBudgetsQuery;
use App\Actions\Budgets\UseCases\DeleteBudgetAction;
use App\Actions\Budgets\UseCases\StoreBudgetAction;
use App\Actions\Budgets\UseCases\UpdateBudgetAction;
use App\Actions\Dashboard\Queries\GetDashboardPageQuery;
use App\Data\Budgets\Input\StoreBudgetData;
use App\Data\Budgets\Input\UpdateBudgetData;
use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Ledger;
use Illuminate\Http\JsonResponse;

class BudgetController extends Controller
{
    public function index(Ledger $ledger, ListBudgetsQuery $listBudgets): JsonResponse
    {
        $this->authorize('view', $ledger);

        return response()->json([
            'data' => $listBudgets($ledger)->map->toArray()->values()->all(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function dashboardTop(Ledger $ledger, GetDashboardPageQuery $getDashboardPage): JsonResponse
    {
        $this->authorize('view', $ledger);

        return response()->json([
            'data' => $getDashboardPage->topBudgets($ledger),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function store(
        Ledger $ledger,
        StoreBudgetData $data,
        StoreBudgetAction $storeBudget,
        GetBudgetDataQuery $getBudgetData,
    ): JsonResponse {
        return response()->json([
            'data' => $getBudgetData($storeBudget($data), $ledger)->toArray(),
        ], 201, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function update(
        Ledger $ledger,
        Budget $budget,
        UpdateBudgetData $data,
        UpdateBudgetAction $updateBudget,
        GetBudgetDataQuery $getBudgetData,
    ): JsonResponse {
        return response()->json([
            'data' => $getBudgetData($updateBudget($data), $ledger)->toArray(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function destroy(
        Ledger $ledger,
        Budget $budget,
        DeleteBudgetAction $deleteBudget,
        GetBudgetDataQuery $getBudgetData,
    ): JsonResponse {
        $this->authorize('delete', $ledger);

        return response()->json([
            'data' => $getBudgetData($deleteBudget($budget), $ledger)->toArray(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}
