<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\DestroyCategoryRequest;
use App\Http\Requests\ReorderRequest;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function index(Request $request, Ledger $ledger): AnonymousResourceCollection
    {
        $this->authorize('view', $ledger);

        $query = $ledger->categories();

        if ($request->boolean('with_counts')) {
            $query->withCount('transactions');
        }

        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->get('transaction_type'));
        }

        if ($request->boolean('flat')) {
            $categories = $query->orderBy('position')->get();
        } else {
            $query->with('children');

            if ($request->boolean('with_counts')) {
                $query->with(['children' => fn ($q) => $q->withCount('transactions')]);
            }

            $categories = $query->parents()->orderBy('position')->get();
        }

        return CategoryResource::collection($categories);
    }

    public function show(Ledger $ledger, Category $category): CategoryResource
    {
        $this->authorize('view', $ledger);

        return new CategoryResource($category->load('children'));
    }

    public function store(StoreCategoryRequest $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        $validated = $request->validated();
        $parentId = $validated['parent_id'] ?? null;

        $positionQuery = $parentId
            ? $ledger->categories()->where('parent_id', $parentId)
            : $ledger->categories()->whereNull('parent_id');

        $category = $ledger->categories()->create([
            'name' => $validated['name'],
            'transaction_type' => $validated['transaction_type'],
            'parent_id' => $parentId,
            'color' => $validated['color'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'position' => $positionQuery->count() + 1,
        ]);

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateCategoryRequest $request, Ledger $ledger, Category $category): CategoryResource
    {
        $this->authorize('update', $ledger);

        $category->update($request->validated());

        return new CategoryResource($category->fresh());
    }

    public function destroy(DestroyCategoryRequest $request, Ledger $ledger, Category $category): JsonResponse
    {
        $this->authorize('delete', $ledger);

        $hasReassignKey = $request->has('reassign_category_id');
        $reassignCategoryId = $request->validated('reassign_category_id');

        if ($category->children()->exists() && ! $hasReassignKey) {
            return response()->json([
                'message' => 'Cannot delete a category that has subcategories. Remove or reassign them first.',
                'errors' => ['category' => ['Cannot delete a category that has subcategories. Remove or reassign them first.']],
            ], 422);
        }

        if ($category->transactions()->exists() && ! $hasReassignKey) {
            return response()->json([
                'message' => 'Cannot delete a category that has transactions. Please reassign them first.',
                'errors' => ['category' => ['Cannot delete a category that has transactions. Please reassign them first.']],
            ], 422);
        }

        DB::transaction(function () use ($category, $reassignCategoryId): void {
            $categoryIds = $category->children()->pluck('id')->push($category->id);

            Transaction::whereIn('category_id', $categoryIds)
                ->update(['category_id' => $reassignCategoryId]);

            $category->delete();
        });

        return response()->json(null, 204);
    }

    public function topSpending(Request $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        $dateFrom = $request->date('date_from');
        $dateTo = $request->date('date_to');

        if ($dateFrom === null || $dateTo === null) {
            return response()->json(['message' => 'date_from and date_to are required.'], 422);
        }

        $limit = $request->integer('limit', 5);

        $dateFromStr = $dateFrom->toDateString();
        $dateToStr = $dateTo->toDateString();

        $query = $ledger->transactions()
            ->whereBetween('transaction_date', [$dateFromStr, $dateToStr])
            ->where('transaction_type', TransactionType::Expense->value);

        if ($request->boolean('exclude_uncategorized')) {
            $query->whereNotNull('category_id');
        }

        $topCategories = $query
            ->select(
                'category_id',
                DB::raw('SUM(amount) as total'),
            )
            ->groupBy('category_id')
            ->orderByRaw('SUM(amount) ASC')
            ->limit($limit)
            ->get();

        $totalExpense = $topCategories->sum(fn ($row) => abs((float) $row->total));

        $categoryLookup = $ledger->categories()
            ->whereIn('id', $topCategories->pluck('category_id')->filter()->all())
            ->get()
            ->keyBy('id');

        $data = $topCategories
            ->map(function ($row) use ($categoryLookup, $totalExpense) {
                $category = $row->category_id
                    ? $categoryLookup->get($row->category_id)
                    : null;

                $absoluteTotal = round(abs((float) $row->total), 2);

                return [
                    'id' => $category?->id,
                    'name' => $category?->name ?? 'Uncategorized',
                    'color' => $category?->color,
                    'total' => $absoluteTotal,
                    'percentage' => $totalExpense > 0 ? round(($absoluteTotal / $totalExpense) * 100, 1) : 0,
                ];
            })
            ->values()
            ->all();

        return response()->json(['data' => $data]);
    }

    public function reorder(ReorderRequest $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('update', $ledger);

        foreach ($request->items as $item) {
            $ledger->categories()->where('id', $item['id'])->update(['position' => $item['position']]);
        }

        return response()->json(['message' => 'Categories reordered successfully.']);
    }
}
