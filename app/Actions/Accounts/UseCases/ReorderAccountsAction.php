<?php

namespace App\Actions\Accounts\UseCases;

use App\Data\Accounts\Input\ReorderAccountsData;
use Illuminate\Support\Facades\DB;

class ReorderAccountsAction
{
    public function __invoke(ReorderAccountsData $data): void
    {
        DB::transaction(function () use ($data): void {
            foreach ($data->items as $item) {
                $data->ledger->accounts()->where('id', $item['id'])->update(['position' => $item['position']]);
            }
        });
    }
}
