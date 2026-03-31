<?php

namespace App\Actions\Tags\UseCases;

use App\Data\Tags\Input\StoreTagData;
use App\Data\Tags\Output\TagData;

class StoreTagAction
{
    public function __invoke(StoreTagData $data): TagData
    {
        $tag = $data->ledger->tags()->firstOrCreate(
            ['name' => $data->name],
            ['color' => $data->color],
        );

        return TagData::fromModel($tag->loadCount('transactions'));
    }
}
