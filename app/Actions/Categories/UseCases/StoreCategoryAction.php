<?php

namespace App\Actions\Categories\UseCases;

use App\Data\Categories\Input\StoreCategoryData;
use App\Data\Categories\Output\CategoryData;

class StoreCategoryAction
{
    public function __invoke(StoreCategoryData $data): CategoryData
    {
        $positionQuery = $data->parent_id !== null
            ? $data->ledger->categories()->where('parent_id', $data->parent_id)
            : $data->ledger->categories()->whereNull('parent_id');

        $category = $data->ledger->categories()->create([
            'name' => $data->name,
            'transaction_type' => $data->transaction_type,
            'parent_id' => $data->parent_id,
            'color' => $data->color,
            'icon' => $data->icon,
            'position' => ((int) $positionQuery->max('position')) + 1,
        ]);

        return CategoryData::fromModel($category->loadCount('transactions'));
    }
}
