<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBudgetRequest;
use App\Http\Requests\UpdateBudgetRequest;
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

        return Inertia::render('ledgers/budgets/index');
    }

    public function store(StoreBudgetRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $validated = $request->validated();

        $this->budgetService->store($ledger, $validated);

        return to_route('ledgers.budgets.index', $ledger)
            ->with('success', 'Budget created.');
    }

    public function update(UpdateBudgetRequest $request, Ledger $ledger, Budget $budget): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $validated = $request->validated();

        $this->budgetService->update($budget, $validated);

        return to_route('ledgers.budgets.index', $ledger)
            ->with('success', 'Budget updated.');
    }

    public function destroy(Request $request, Ledger $ledger, Budget $budget): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $budget->delete();

        return to_route('ledgers.budgets.index', $ledger)
            ->with('success', 'Budget deleted.');
    }
}
