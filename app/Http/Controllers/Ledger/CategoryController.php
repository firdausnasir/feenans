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

        $ledger->categories()->create([
            ...$request->validated(),
            'position' => $ledger->categories()->count() + 1,
        ]);

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

        $reassignCategoryId = $request->validated('reassign_category_id');

        DB::transaction(function () use ($category, $reassignCategoryId) {
            // Reassign transactions from this category (and its children) to the target or null
            $categoryIds = $category->children()->pluck('id')->push($category->id);

            Transaction::whereIn('category_id', $categoryIds)
                ->update(['category_id' => $reassignCategoryId]);

            $category->delete();
        });

        return back();
    }

    public function trash(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/categories/trash/index', [
            'ledger' => $ledger,
            'categories' => $ledger->categories()
                ->onlyTrashed()
                ->orderByDesc('deleted_at')
                ->get(),
        ]);
    }

    public function restore(Request $request, Ledger $ledger, Category $category): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $category->restore();

        return to_route('ledgers.categories.trash', $ledger);
    }

    public function forceDestroy(Request $request, Ledger $ledger, Category $category): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $category->forceDelete();

        return to_route('ledgers.categories.trash', $ledger);
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
