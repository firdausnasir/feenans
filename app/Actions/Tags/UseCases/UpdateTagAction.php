<?php

namespace App\Actions\Tags\UseCases;

use App\Data\Tags\Input\UpdateTagData;
use App\Data\Tags\Output\TagData;

class UpdateTagAction
{
    public function __invoke(UpdateTagData $data): TagData
    {
        $data->tag->update([
            'name' => $data->name,
            'color' => $data->color,
        ]);

        return TagData::fromModel($data->tag->fresh()->loadCount('transactions'));
    }
}
