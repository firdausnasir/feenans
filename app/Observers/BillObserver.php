<?php

namespace App\Observers;

use App\Models\Bill;
use App\Services\ActivityLogService;

class BillObserver
{
    public function __construct(protected ActivityLogService $activityLogService) {}

    public function created(Bill $bill): void
    {
        $this->activityLogService->log('created', $bill, [], $bill->getAttributes());
    }

    public function updated(Bill $bill): void
    {
        $this->activityLogService->log('updated', $bill, $bill->getOriginal(), $bill->getAttributes());
    }

    public function deleted(Bill $bill): void
    {
        $this->activityLogService->log('deleted', $bill, $bill->getOriginal(), []);
    }

    public function restored(Bill $bill): void
    {
        $this->activityLogService->log('restored', $bill, [], $bill->getAttributes());
    }
}
