<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Ledger;
use App\Models\User;
use App\Notifications\BudgetExceeded;
use App\Notifications\BudgetThresholdReached;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\DatabaseNotification;

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
                $ledger->cycleBounds($today)['start'],
                $ledger->cycleBounds($today)['end'],
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

        foreach ($budgets as $budget) {
            $allocated = (float) $budget->amount;

            if ($allocated <= 0) {
                continue;
            }

            $spent = $this->getSpent($budget, $ledger);
            $percentage = round(($spent / $allocated) * 100, 1);

            if ($percentage >= 100 && ! $this->hasUnreadBudgetNotification($user, $budget->id, 'budget_exceeded')) {
                $user->notify(new BudgetExceeded($budget, $percentage, $spent));
            } elseif ($percentage >= 80 && $percentage < 100 && ! $this->hasUnreadBudgetNotification($user, $budget->id, 'budget_threshold')) {
                $user->notify(new BudgetThresholdReached($budget, $percentage, $spent));
            }
        }
    }

    private function hasUnreadBudgetNotification(User $user, int $budgetId, string $type): bool
    {
        return $user->unreadNotifications()
            ->get()
            ->contains(function (DatabaseNotification $notification) use ($budgetId, $type): bool {
                $data = $notification->data;

                return ($data['type'] ?? null) === $type
                    && ($data['budget_id'] ?? null) === $budgetId;
            });
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

        return $budgets->map(function (Budget $budget) use ($ledger) {
            $allocated = (float) $budget->amount;
            $spent = $this->getSpent($budget, $ledger);
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
}
