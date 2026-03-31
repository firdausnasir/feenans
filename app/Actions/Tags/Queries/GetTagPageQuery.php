<?php

namespace App\Actions\Tags\Queries;

use App\Data\Tags\Output\TagPageData;
use App\Models\Ledger;

class GetTagPageQuery
{
    public function __construct(private ListTagsQuery $listTags) {}

    public function __invoke(Ledger $ledger): TagPageData
    {
        return new TagPageData(tags: ($this->listTags)($ledger));
    }
}
