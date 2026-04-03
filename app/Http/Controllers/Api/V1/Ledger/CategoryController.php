<?php

namespace App\Http\Controllers\Api\V1\Ledger;

use App\Actions\Categories\Queries\ListCategoriesQuery;
use App\Actions\Categories\UseCases\DeleteCategoryAction;
use App\Actions\Categories\UseCases\ReorderCategoriesAction;
use App\Actions\Categories\UseCases\StoreCategoryAction;
use App\Actions\Categories\UseCases\UpdateCategoryAction;
use App\Actions\Dashboard\Queries\GetDashboardPageQuery;
use App\Data\Categories\Input\DestroyCategoryData;
use App\Data\Categories\Input\ReorderCategoriesData;
use App\Data\Categories\Input\StoreCategoryData;
use App\Data\Categories\Input\UpdateCategoryData;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Ledger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Ledger $ledger, ListCategoriesQuery $listCategories): JsonResponse
    {
        $this->authorize('view', $ledger);

        return response()->json([
            'data' => $listCategories($ledger)->map->toArray()->values()->all(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function dashboardTop(
        Ledger $ledger,
        Request $request,
        GetDashboardPageQuery $getDashboardPage,
    ): JsonResponse {
        $this->authorize('view', $ledger);

        return response()->json([
            'data' => $getDashboardPage->topCategories($ledger, $request->integer('offset', 0)),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function dashboardUncategorizedCount(
        Ledger $ledger,
        Request $request,
        GetDashboardPageQuery $getDashboardPage,
    ): JsonResponse {
        $this->authorize('view', $ledger);

        return response()->json([
            'data' => [
                'count' => $getDashboardPage->uncategorizedCount($ledger, $request->integer('offset', 0)),
            ],
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function store(Ledger $ledger, StoreCategoryData $data, StoreCategoryAction $storeCategory): JsonResponse
    {
        return response()->json([
            'data' => $storeCategory($data)->toArray(),
        ], 201, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function update(Ledger $ledger, Category $category, UpdateCategoryData $data, UpdateCategoryAction $updateCategory): JsonResponse
    {
        return response()->json([
            'data' => $updateCategory($data)->toArray(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function destroy(Ledger $ledger, Category $category, DestroyCategoryData $data, DeleteCategoryAction $deleteCategory): JsonResponse
    {
        return response()->json([
            'data' => $deleteCategory($data)->toArray(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function reorder(Ledger $ledger, ReorderCategoriesData $data, ReorderCategoriesAction $reorderCategories): JsonResponse
    {
        $reorderCategories($data);

        return response()->json(status: 204);
    }
}
