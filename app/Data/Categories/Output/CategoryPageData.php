<?php

namespace App\Data\Categories\Output;

use App\Data\Shared\Output\BaseOutputData;
use Illuminate\Support\Collection;

class CategoryPageData extends BaseOutputData
{
    /**
     * @param  Collection<int, CategoryData>  $categories
     */
    public function __construct(public Collection $categories) {}

    /**
     * @return array{categories: array<int, array<string, mixed>>}
     */
    public function toInertiaProps(): array
    {
        return [
            'categories' => $this->categories->map(fn (CategoryData $category) => $category->toArray())->values()->all(),
        ];
    }
}
