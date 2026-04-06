<?php

namespace App\Actions\Categories\UseCases;

use App\Data\Categories\Input\ReorderCategoriesData;
use App\Services\ReorderPositionsService;
use Illuminate\Support\Facades\DB;

class ReorderCategoriesAction
{
    public function __construct(private readonly ReorderPositionsService $reorderPositions) {}

    public function __invoke(ReorderCategoriesData $data): void
    {
        DB::transaction(function () use ($data): void {
            ($this->reorderPositions)(
                $data->ledger->categories()->getModel()->getTable(),
                $data->ledger->id,
                $data->items,
            );
        });
    }
}
