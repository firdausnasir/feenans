<?php

namespace App\Data\Tags\Output;

use App\Data\Shared\Output\BaseOutputData;
use Illuminate\Support\Collection;

class TagPageData extends BaseOutputData
{
    /**
     * @param  Collection<int, TagData>  $tags
     */
    public function __construct(public Collection $tags) {}

    /**
     * @return array{tags: array<int, array<string, mixed>>}
     */
    public function toInertiaProps(): array
    {
        return [
            'tags' => $this->tags->map(fn (TagData $tag) => $tag->toArray())->values()->all(),
        ];
    }
}
