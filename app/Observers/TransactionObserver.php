<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Services\ActivityLogService;

class TransactionObserver
{
    public function __construct(protected ActivityLogService $activityLogService) {}

    public function created(Transaction $transaction): void
    {
        $this->activityLogService->log('created', $transaction, [], $transaction->getAttributes());
    }

    public function updated(Transaction $transaction): void
    {
        $this->activityLogService->log('updated', $transaction, $transaction->getOriginal(), $transaction->getAttributes());
    }

    public function deleted(Transaction $transaction): void
    {
        $this->activityLogService->log('deleted', $transaction, $transaction->getOriginal(), []);
    }

    public function restored(Transaction $transaction): void
    {
        $this->activityLogService->log('restored', $transaction, [], $transaction->getAttributes());
    }
}
