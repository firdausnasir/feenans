<?php

namespace App\Actions\Tags\UseCases;

use App\Data\Tags\Output\TagData;
use App\Models\Tag;

class DeleteTagAction
{
    public function __invoke(Tag $tag): TagData
    {
        $tagData = TagData::fromModel($tag->loadCount('transactions'));

        $tag->delete();

        return $tagData;
    }
}
