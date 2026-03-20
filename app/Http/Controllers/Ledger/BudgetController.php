<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBudgetRequest;
use App\Http\Requests\UpdateBudgetRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Budget;
use App\Models\Ledger;
use App\Services\BudgetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function __construct(private readonly BudgetService $budgetService) {}

    public function index(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/budgets/index', [
            'categories' => fn () => CategoryResource::collection(
                $ledger->categories()
                    ->with('children')
                    ->parents()
                    ->orderBy('position')
                    ->get()
            )->resolve(),
            'budgets' => Inertia::defer(fn () => $this->budgetService->getBudgetsWithStats($ledger)),
        ]);
    }

    public function store(StoreBudgetRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $this->budgetService->store($ledger, $request->validated());

        return to_route('ledgers.budgets.index', $ledger)->with('success', 'Budget created.');
    }

    public function update(UpdateBudgetRequest $request, Ledger $ledger, Budget $budget): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $this->budgetService->update($budget, $request->validated());

        return to_route('ledgers.budgets.index', $ledger)->with('success', 'Budget updated.');
    }

    public function destroy(Ledger $ledger, Budget $budget): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $budget->delete();

        return to_route('ledgers.budgets.index', $ledger)->with('success', 'Budget deleted.');
    }
}
