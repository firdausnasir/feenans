<?php

namespace App\Actions\Accounts\UseCases;

use App\Data\Accounts\Input\ReorderAccountsData;
use App\Services\ReorderPositionsService;
use Illuminate\Support\Facades\DB;

class ReorderAccountsAction
{
    public function __construct(private readonly ReorderPositionsService $reorderPositions) {}

    public function __invoke(ReorderAccountsData $data): void
    {
        DB::transaction(function () use ($data): void {
            ($this->reorderPositions)(
                $data->ledger->accounts()->getModel()->getTable(),
                $data->ledger->id,
                $data->items,
            );
        });
    }
}
