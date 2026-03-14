<?php

namespace App\Observers;

use App\Models\Account;
use App\Services\ActivityLogService;

class AccountObserver
{
    public function __construct(protected ActivityLogService $activityLogService) {}

    public function created(Account $account): void
    {
        $this->activityLogService->log('created', $account, [], $account->getAttributes());
    }

    public function updated(Account $account): void
    {
        $this->activityLogService->log('updated', $account, $account->getOriginal(), $account->getAttributes());
    }

    public function deleted(Account $account): void
    {
        $this->activityLogService->log('deleted', $account, $account->getOriginal(), []);
    }

    public function restored(Account $account): void
    {
        $this->activityLogService->log('restored', $account, [], $account->getAttributes());
    }
}
