<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\ActivityLogService;

class CategoryObserver
{
    public function __construct(protected ActivityLogService $activityLogService) {}

    public function created(Category $category): void
    {
        $this->activityLogService->log('created', $category, [], $category->getAttributes());
    }

    public function updated(Category $category): void
    {
        $this->activityLogService->log('updated', $category, $category->getOriginal(), $category->getAttributes());
    }

    public function deleted(Category $category): void
    {
        $this->activityLogService->log('deleted', $category, $category->getOriginal(), []);
    }

    public function restored(Category $category): void
    {
        $this->activityLogService->log('restored', $category, [], $category->getAttributes());
    }
}
