<?php

namespace App\Actions\Budgets\Queries;

use App\Models\Budget;
use App\Models\Ledger;
use Illuminate\Support\Collection;

class GetBudgetSpendMapQuery
{
    public function __construct(private readonly GetBudgetPeriodBoundsQuery $getBudgetPeriodBounds) {}

    /**
     * @param  Collection<int, Budget>  $budgets
     * @return array<int, float>
     */
    public function __invoke(Collection $budgets, Ledger $ledger): array
    {
        if ($budgets->isEmpty()) {
            return [];
        }

        $spentByBudgetId = [];

        $budgets
            ->groupBy(fn (Budget $budget): string => $this->getPeriodBoundsKey($budget, $ledger))
            ->each(function (Collection $periodBudgets) use ($ledger, &$spentByBudgetId): void {
                /** @var Budget $periodBudget */
                $periodBudget = $periodBudgets->first();
                [$periodStart, $periodEnd] = ($this->getBudgetPeriodBounds)($periodBudget, $ledger);

                /** @var Collection<int, object> $periodSpendRows */
                $periodSpendRows = $ledger->transactions()
                    ->selectRaw('category_id, ABS(SUM(amount)) as spent')
                    ->where('transaction_date', '>=', $periodStart->toDateString())
                    ->where('transaction_date', '<=', $periodEnd->toDateString())
                    ->where('amount', '<', 0)
                    ->groupBy('category_id')
                    ->get();

                /** @var array{categories: array<int, float>, total: float} $periodSpend */
                $periodSpend = $periodSpendRows->reduce(
                    function (array $carry, object $row): array {
                        $spent = (float) $row->spent;

                        if ($row->category_id !== null) {
                            $carry['categories'][(int) $row->category_id] = $spent;
                        }

                        $carry['total'] += $spent;

                        return $carry;
                    },
                    ['categories' => [], 'total' => 0.0],
                );

                foreach ($periodBudgets as $budget) {
                    $spentByBudgetId[$budget->id] = $budget->category_id === null
                        ? $periodSpend['total']
                        : (float) ($periodSpend['categories'][$budget->category_id] ?? 0.0);
                }
            });

        return $spentByBudgetId;
    }

    private function getPeriodBoundsKey(Budget $budget, Ledger $ledger): string
    {
        [$periodStart, $periodEnd] = ($this->getBudgetPeriodBounds)($budget, $ledger);

        return $periodStart->toDateString().'|'.$periodEnd->toDateString();
    }
}
