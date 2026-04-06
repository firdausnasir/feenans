<?php

namespace App\Actions\Categories\Queries;

use App\Data\Categories\Output\CategoryPageData;
use App\Models\Ledger;

class GetCategoryPageQuery
{
    public function __construct(private ListCategoriesQuery $listCategories) {}

    public function __invoke(Ledger $ledger): CategoryPageData
    {
        return new CategoryPageData(categories: ($this->listCategories)($ledger));
    }
}
