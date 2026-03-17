<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBudgetRequest;
use App\Http\Requests\UpdateBudgetRequest;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use App\Models\Ledger;
use App\Services\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BudgetController extends Controller
{
    public function __construct(private readonly BudgetService $budgetService) {}

    public function index(Request $request, Ledger $ledger): AnonymousResourceCollection
    {
        $this->authorize('view', $ledger);

        if ($request->boolean('with_stats')) {
            $stats = $this->budgetService->getBudgetsWithStats($ledger);

            if ($request->filled('top')) {
                $top = $request->integer('top');
                $stats = collect($stats)
                    ->sortByDesc('percentage')
                    ->take($top)
                    ->values()
                    ->all();
            }

            return BudgetResource::collection($stats);
        }

        $budgets = $ledger->budgets()
            ->with('category')
            ->where('is_active', true)
            ->get();

        return BudgetResource::collection($budgets);
    }

    public function show(Ledger $ledger, Budget $budget): BudgetResource
    {
        $this->authorize('view', $ledger);

        $budget->load('category');

        $allocated = (float) $budget->amount;
        $spent = $this->budgetService->getSpent($budget, $ledger);
        $remaining = max(0, $allocated - $spent);
        $percentage = $allocated > 0 ? min(100, round(($spent / $allocated) * 100, 1)) : 0;
        [$periodStart, $periodEnd] = $this->budgetService->getPeriodBounds($budget, $ledger);

        return new BudgetResource([
            'id' => $budget->id,
            'category_id' => $budget->category_id,
            'category_name' => $budget->category?->name ?? 'Overall',
            'category_color' => $budget->category?->color,
            'amount' => $allocated,
            'period' => $budget->period,
            'spent' => $spent,
            'remaining' => $remaining,
            'percentage' => $percentage,
            'status' => $percentage >= 100 ? 'over' : ($percentage >= 90 ? 'danger' : ($percentage >= 75 ? 'warning' : 'good')),
            'rollover' => $budget->rollover,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'start_date' => $budget->start_date?->toDateString(),
        ]);
    }

    public function store(StoreBudgetRequest $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('update', $ledger);

        $budget = $this->budgetService->store($ledger, $request->validated());

        return (new BudgetResource($budget->load('category')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateBudgetRequest $request, Ledger $ledger, Budget $budget): BudgetResource
    {
        $this->authorize('update', $ledger);

        $updated = $this->budgetService->update($budget, $request->validated());

        return new BudgetResource($updated->load('category'));
    }

    public function destroy(Ledger $ledger, Budget $budget): JsonResponse
    {
        $this->authorize('delete', $ledger);

        $budget->delete();

        return response()->json(null, 204);
    }
}
