<?php

namespace App\Http\Controllers\Ledger;

use App\Actions\Categories\Queries\GetCategoryPageQuery;
use App\Actions\Categories\UseCases\DeleteCategoryAction;
use App\Actions\Categories\UseCases\ReorderCategoriesAction;
use App\Actions\Categories\UseCases\StoreCategoryAction;
use App\Actions\Categories\UseCases\UpdateCategoryAction;
use App\Data\Categories\Input\DestroyCategoryData;
use App\Data\Categories\Input\ReorderCategoriesData;
use App\Data\Categories\Input\StoreCategoryData;
use App\Data\Categories\Input\UpdateCategoryData;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Ledger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request, Ledger $ledger, GetCategoryPageQuery $getCategoryPage): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/categories/index', [
            'categories' => Inertia::defer(function () use ($ledger, $getCategoryPage) {
                return $getCategoryPage($ledger)->toInertiaProps()['categories'];
            }),
        ]);
    }

    public function store(Ledger $ledger, StoreCategoryData $data, StoreCategoryAction $storeCategory): RedirectResponse
    {
        $storeCategory($data);

        return back()->with('success', 'Category created.');
    }

    public function update(Ledger $ledger, Category $category, UpdateCategoryData $data, UpdateCategoryAction $updateCategory): RedirectResponse
    {
        $updateCategory($data);

        return back()->with('success', 'Category updated.');
    }

    public function destroy(Ledger $ledger, Category $category, DestroyCategoryData $data, DeleteCategoryAction $deleteCategory): RedirectResponse
    {
        $deleteCategory($data);

        return back()->with('success', 'Category deleted.');
    }

    public function reorder(Ledger $ledger, ReorderCategoriesData $data, ReorderCategoriesAction $reorderCategories): RedirectResponse
    {
        $reorderCategories($data);

        return back();
    }
}
