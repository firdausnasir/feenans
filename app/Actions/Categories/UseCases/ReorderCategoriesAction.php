<?php

namespace App\Actions\Categories\UseCases;

use App\Data\Categories\Input\ReorderCategoriesData;
use Illuminate\Support\Facades\DB;

class ReorderCategoriesAction
{
    public function __invoke(ReorderCategoriesData $data): void
    {
        DB::transaction(function () use ($data): void {
            foreach ($data->items as $item) {
                $data->ledger->categories()->where('id', $item['id'])->update(['position' => $item['position']]);
            }
        });
    }
}
