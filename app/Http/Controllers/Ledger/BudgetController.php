<?php

namespace App\Http\Controllers\Ledger;

use App\Actions\Budgets\Queries\GetBudgetPageQuery;
use App\Actions\Budgets\UseCases\DeleteBudgetAction;
use App\Actions\Budgets\UseCases\StoreBudgetAction;
use App\Actions\Budgets\UseCases\UpdateBudgetAction;
use App\Actions\Categories\Queries\ListCategoriesQuery;
use App\Data\Budgets\Input\StoreBudgetData;
use App\Data\Budgets\Input\UpdateBudgetData;
use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Ledger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function index(
        Ledger $ledger,
        ListCategoriesQuery $listCategories,
        GetBudgetPageQuery $getBudgetPage,
    ): Response {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/budgets/index', [
            'categories' => $listCategories($ledger)->map->toArray()->values()->all(),
            'budgets' => Inertia::defer(function () use ($ledger, $getBudgetPage) {
                return $getBudgetPage($ledger)->toInertiaProps()['budgets'];
            }),
        ]);
    }

    public function store(Ledger $ledger, StoreBudgetData $data, StoreBudgetAction $storeBudget): RedirectResponse
    {
        $storeBudget($data);

        return to_route('ledgers.budgets.index', $ledger)->with('success', 'Budget created.');
    }

    public function update(Ledger $ledger, Budget $budget, UpdateBudgetData $data, UpdateBudgetAction $updateBudget): RedirectResponse
    {
        $updateBudget($data);

        return to_route('ledgers.budgets.index', $ledger)->with('success', 'Budget updated.');
    }

    public function destroy(Ledger $ledger, Budget $budget, DeleteBudgetAction $deleteBudget): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $deleteBudget($budget);

        return to_route('ledgers.budgets.index', $ledger)->with('success', 'Budget deleted.');
    }
}
