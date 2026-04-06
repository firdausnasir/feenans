<?php

namespace App\Actions\Categories\UseCases;

use App\Data\Categories\Input\UpdateCategoryData;
use App\Data\Categories\Output\CategoryData;

class UpdateCategoryAction
{
    public function __invoke(UpdateCategoryData $data): CategoryData
    {
        $data->category->update($data->attributesToUpdate());

        $category = $data->category->fresh()->loadCount('transactions');

        if ($category->parent_id === null) {
            $category->load([
                'children' => fn ($query) => $query
                    ->withCount('transactions')
                    ->orderBy('position'),
            ]);
        }

        return CategoryData::fromModel($category);
    }
}
