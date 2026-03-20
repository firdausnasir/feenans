<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/categories/index', [
            'categories' => Inertia::defer(function () use ($ledger): array {
                $categories = $ledger
                    ->categories()
                    ->with('children')
                    ->withCount('transactions')
                    ->with(['children' => fn ($q) => $q->withCount('transactions')])
                    ->parents()
                    ->orderBy('position')
                    ->get();

                return CategoryResource::collection($categories)->resolve();
            }),
        ]);
    }

    public function store(Request $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('view', $ledger);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'transaction_type' => ['required', 'string', 'in:expense,income'],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('ledger_id', $ledger->id)],
            'color' => ['nullable', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:50'],
        ]);

        $parentId = $validated['parent_id'] ?? null;

        $positionQuery = $parentId
            ? $ledger->categories()->where('parent_id', $parentId)
            : $ledger->categories()->whereNull('parent_id');

        $ledger->categories()->create([
            'name' => $validated['name'],
            'transaction_type' => $validated['transaction_type'],
            'parent_id' => $parentId,
            'color' => $validated['color'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'position' => $positionQuery->count() + 1,
        ]);

        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, Ledger $ledger, Category $category): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('ledger_id', $ledger->id)],
            'color' => ['nullable', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:50'],
        ]);

        $category->update($validated);

        return back()->with('success', 'Category updated.');
    }

    public function destroy(Request $request, Ledger $ledger, Category $category): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $hasReassignKey = $request->has('reassign_category_id');
        $reassignCategoryId = $request->input('reassign_category_id');

        if ($category->children()->exists() && ! $hasReassignKey) {
            return back()->withErrors([
                'category' => 'Cannot delete a category that has subcategories. Remove or reassign them first.',
            ]);
        }

        if ($category->transactions()->exists() && ! $hasReassignKey) {
            return back()->withErrors([
                'category' => 'Cannot delete a category that has transactions. Please reassign them first.',
            ]);
        }

        DB::transaction(function () use ($category, $reassignCategoryId): void {
            $categoryIds = $category->children()->pluck('id')->push($category->id);

            Transaction::whereIn('category_id', $categoryIds)
                ->update(['category_id' => $reassignCategoryId]);

            $category->delete();
        });

        return back()->with('success', 'Category deleted.');
    }

    public function reorder(Request $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', Rule::exists('categories', 'id')->where('ledger_id', $ledger->id)],
            'items.*.position' => ['required', 'integer', 'min:1'],
        ]);

        foreach ($validated['items'] as $item) {
            $ledger->categories()->where('id', $item['id'])->update(['position' => $item['position']]);
        }

        return back();
    }
}
