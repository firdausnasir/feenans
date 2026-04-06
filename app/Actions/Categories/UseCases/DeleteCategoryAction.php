<?php

namespace App\Actions\Categories\UseCases;

use App\Data\Categories\Input\DestroyCategoryData;
use App\Data\Categories\Output\CategoryData;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteCategoryAction
{
    public function __invoke(DestroyCategoryData $data): CategoryData
    {
        if ($data->category->children()->exists() && ! $data->hasReassignmentInstruction()) {
            throw ValidationException::withMessages([
                'category' => 'Cannot delete a category that has subcategories. Remove or reassign them first.',
            ]);
        }

        if ($data->category->transactions()->exists() && ! $data->hasReassignmentInstruction()) {
            throw ValidationException::withMessages([
                'category' => 'Cannot delete a category that has transactions. Please reassign them first.',
            ]);
        }

        $category = $data->category->loadCount('transactions');

        if ($category->parent_id === null) {
            $category->load([
                'children' => fn ($query) => $query
                    ->withCount('transactions')
                    ->orderBy('position'),
            ]);
        }

        $categoryData = CategoryData::fromModel($category);

        DB::transaction(function () use ($data): void {
            $categoryIds = $data->category->children()->pluck('id')->push($data->category->id);

            Transaction::whereIn('category_id', $categoryIds)
                ->update(['category_id' => $data->reassign_category_id]);

            $data->category->delete();
        });

        return $categoryData;
    }
}
