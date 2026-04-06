<?php

namespace App\Actions\Budgets\UseCases;

use App\Actions\Budgets\Queries\GetBudgetSpendMapQuery;
use App\Models\Ledger;
use App\Models\User;
use App\Notifications\BudgetExceeded;
use App\Notifications\BudgetThresholdReached;
use Illuminate\Notifications\DatabaseNotification;

class CheckBudgetThresholdsAction
{
    public function __construct(private readonly GetBudgetSpendMapQuery $getBudgetSpendMap) {}

    public function __invoke(Ledger $ledger, ?int $categoryId = null): void
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
        $spentByBudgetId = ($this->getBudgetSpendMap)($budgets, $ledger);
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

                continue;
            }

            if ($percentage >= 80 && ! ($unreadBudgetNotifications[$this->budgetNotificationLookupKey($budget->id, 'budget_threshold')] ?? false)) {
                $user->notify(new BudgetThresholdReached($budget, $percentage, $spent));
                $unreadBudgetNotifications[$this->budgetNotificationLookupKey($budget->id, 'budget_threshold')] = true;
            }
        }
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
