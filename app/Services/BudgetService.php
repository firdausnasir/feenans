<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Ledger;
use App\Models\User;
use App\Notifications\BudgetExceeded;
use App\Notifications\BudgetThresholdReached;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

class BudgetService
{
    public function store(Ledger $ledger, array $data): Budget
    {
        return $ledger->budgets()->create([
            'category_id' => $data['category_id'] ?? null,
            'amount' => $data['amount'],
            'period' => $data['period'] ?? 'monthly',
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'rollover' => $data['rollover'] ?? false,
        ]);
    }

    public function update(Budget $budget, array $data): Budget
    {
        $budget->update([
            'category_id' => $data['category_id'] ?? null,
            'amount' => $data['amount'],
            'period' => $data['period'] ?? $budget->period,
            'start_date' => $data['start_date'] ?? $budget->start_date,
            'end_date' => $data['end_date'] ?? null,
            'is_active' => $data['is_active'] ?? $budget->is_active,
            'rollover' => $data['rollover'] ?? $budget->rollover,
        ]);

        return $budget->fresh();
    }

    /**
     * Calculate how much has been spent for a budget in the current period.
     */
    public function getSpent(Budget $budget, Ledger $ledger): float
    {
        [$start, $end] = $this->getPeriodBounds($budget, $ledger);

        $query = $ledger->transactions()
            ->where('transaction_date', '>=', $start)
            ->where('transaction_date', '<=', $end)
            ->where('amount', '<', 0); // expenses only

        if ($budget->category_id !== null) {
            $query->where('category_id', $budget->category_id);
        }

        return (float) abs($query->sum('amount'));
    }

    /**
     * Get the start and end dates for the current budget period.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function getPeriodBounds(Budget $budget, Ledger $ledger): array
    {
        $today = CarbonImmutable::today();
        $cycleBounds = $ledger->cycleBounds($today);

        return match ($budget->period) {
            'weekly' => [
                $today->startOfWeek(),
                $today->endOfWeek(),
            ],
            'yearly' => [
                $today->startOfYear(),
                $today->endOfYear(),
            ],
            default => [
                $cycleBounds['start'],
                $cycleBounds['end'],
            ],
        };
    }

    /**
     * Check budget thresholds for a ledger and send notifications when exceeded.
     */
    public function checkThresholds(Ledger $ledger, ?int $categoryId = null): void
    {
        $user = $ledger->user;

        if (! $user instanceof User) {
            return;
        }

        $query = $ledger->budgets()
            ->with('category')
            ->where('is_active', true);

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        $budgets = $query->get();
        $spentByBudgetId = $this->getSpentByBudgetId($budgets, $ledger);
        $unreadBudgetNotifications = $this->getUnreadBudgetNotificationLookup($user);

        foreach ($budgets as $budget) {
            $allocated = (float) $budget->amount;

            if ($allocated <= 0) {
                continue;
            }

            $spent = $spentByBudgetId[$budget->id] ?? 0.0;
            $percentage = round(($spent / $allocated) * 100, 1);

            if ($percentage >= 100 && ! ($unreadBudgetNotifications[$this->budgetNotificationLookupKey($budget->id, 'budget_exceeded')] ?? false)) {
                $user->notify(new BudgetExceeded($budget, $percentage, $spent));
                $unreadBudgetNotifications[$this->budgetNotificationLookupKey($budget->id, 'budget_exceeded')] = true;
            } elseif ($percentage >= 80 && $percentage < 100 && ! ($unreadBudgetNotifications[$this->budgetNotificationLookupKey($budget->id, 'budget_threshold')] ?? false)) {
                $user->notify(new BudgetThresholdReached($budget, $percentage, $spent));
                $unreadBudgetNotifications[$this->budgetNotificationLookupKey($budget->id, 'budget_threshold')] = true;
            }
        }
    }

    /**
     * Build enriched budget data with spent/remaining/percentage for a ledger.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBudgetsWithStats(Ledger $ledger): array
    {
        $budgets = $ledger->budgets()
            ->with('category')
            ->where('is_active', true)
            ->get();
        $spentByBudgetId = $this->getSpentByBudgetId($budgets, $ledger);

        return $budgets->map(function (Budget $budget) use ($ledger, $spentByBudgetId) {
            $allocated = (float) $budget->amount;
            $spent = $spentByBudgetId[$budget->id] ?? 0.0;
            $remaining = max(0, $allocated - $spent);
            $percentage = $allocated > 0 ? min(100, round(($spent / $allocated) * 100, 1)) : 0;
            [$periodStart, $periodEnd] = $this->getPeriodBounds($budget, $ledger);

            return [
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
            ];
        })->all();
    }

    /**
     * @param  Collection<int, Budget>  $budgets
     * @return array<int, float>
     */
    private function getSpentByBudgetId(Collection $budgets, Ledger $ledger): array
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
                [$periodStart, $periodEnd] = $this->getPeriodBounds($periodBudget, $ledger);

                /** @var Collection<int, object> $periodSpendRows */
                $periodSpendRows = $ledger->transactions()
                    ->selectRaw('category_id, ABS(SUM(amount)) as spent')
                    ->where('transaction_date', '>=', $periodStart)
                    ->where('transaction_date', '<=', $periodEnd)
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
        [$periodStart, $periodEnd] = $this->getPeriodBounds($budget, $ledger);

        return $periodStart->toDateString().'|'.$periodEnd->toDateString();
    }

    /**
     * @return array<string, bool>
     */
    private function getUnreadBudgetNotificationLookup(User $user): array
    {
        $lookup = [];

        $user->unreadNotifications()
            ->get()
            ->each(function (DatabaseNotification $notification) use (&$lookup): void {
                $data = $notification->data;
                $type = $data['type'] ?? null;
                $budgetId = $data['budget_id'] ?? null;

                if (! is_string($type) || ! is_numeric($budgetId)) {
                    return;
                }

                $lookup[$this->budgetNotificationLookupKey((int) $budgetId, $type)] = true;
            });

        return $lookup;
    }

    private function budgetNotificationLookupKey(int $budgetId, string $type): string
    {
        return $type.'|'.$budgetId;
    }
}
