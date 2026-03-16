<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\DestroyCategoryRequest;
use App\Http\Requests\ReorderRequest;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/categories/index', [
            'ledger' => $ledger,
            'categories' => $ledger->categories()
                ->withCount('transactions')
                ->with(['children' => fn ($q) => $q->withCount('transactions')])
                ->parents()
                ->orderBy('position')
                ->get(),
        ]);
    }

    public function create(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/categories/create', [
            'ledger' => $ledger,
        ]);
    }

    public function store(StoreCategoryRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('view', $ledger);

        $validated = $request->validated();
        $parentId = $validated['parent_id'] ?? null;

        $positionQuery = $parentId
            ? $ledger->categories()->where('parent_id', $parentId)
            : $ledger->categories()->whereNull('parent_id');

        $ledger->categories()->upsert(
            [
                [
                    'ledger_id' => $ledger->id,
                    'name' => $validated['name'],
                    'transaction_type' => $validated['transaction_type'],
                    'parent_id' => $parentId,
                    'color' => $validated['color'] ?? null,
                    'icon' => $validated['icon'] ?? null,
                    'position' => $positionQuery->count() + 1,
                ],
            ],
            ['ledger_id', 'transaction_type', 'name'],
            ['parent_id', 'color', 'icon', 'position'],
        );

        return back();
    }

    public function update(UpdateCategoryRequest $request, Ledger $ledger, Category $category): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $category->update($request->validated());

        return back();
    }

    public function destroy(DestroyCategoryRequest $request, Ledger $ledger, Category $category): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $hasReassignKey = $request->has('reassign_category_id');
        $reassignCategoryId = $request->validated('reassign_category_id');

        // Reject deletion if category has children and no explicit reassignment acknowledged
        if ($category->children()->exists() && ! $hasReassignKey) {
            return back()->withErrors(['category' => 'Cannot delete a category that has subcategories. Remove or reassign them first.']);
        }

        // Reject deletion if category has transactions and no explicit reassignment acknowledged
        if ($category->transactions()->exists() && ! $hasReassignKey) {
            return back()->withErrors(['category' => 'Cannot delete a category that has transactions. Please reassign them first.']);
        }

        DB::transaction(function () use ($category, $reassignCategoryId) {
            $categoryIds = $category->children()->pluck('id')->push($category->id);

            Transaction::whereIn('category_id', $categoryIds)
                ->update(['category_id' => $reassignCategoryId]);

            // Hard delete — children cascade via DB foreign key
            $category->delete();
        });

        return back();
    }

    public function reorder(ReorderRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('update', $ledger);

        foreach ($request->items as $item) {
            $ledger->categories()->where('id', $item['id'])->update(['position' => $item['position']]);
        }

        return back();
    }
}
