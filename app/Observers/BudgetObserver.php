<?php

namespace App\Observers;

use App\Models\Budget;
use App\Services\ActivityLogService;

class BudgetObserver
{
    public function __construct(protected ActivityLogService $activityLogService) {}

    public function created(Budget $budget): void
    {
        $this->activityLogService->log('created', $budget, [], $budget->getAttributes());
    }

    public function updated(Budget $budget): void
    {
        $this->activityLogService->log('updated', $budget, $budget->getOriginal(), $budget->getAttributes());
    }

    public function deleted(Budget $budget): void
    {
        $this->activityLogService->log('deleted', $budget, $budget->getOriginal(), []);
    }
}
