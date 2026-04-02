<?php

namespace App\Data\Tags\Output;

use App\Data\Shared\Output\BaseOutputData;
use App\Models\Tag;

class TagData extends BaseOutputData
{
    public function __construct(
        public int $id,
        public int $ledger_id,
        public string $name,
        public ?string $color,
        public ?int $transactions_count,
        public ?string $created_at,
        public ?string $updated_at,
    ) {}

    public static function fromModel(Tag $tag): self
    {
        $attributes = $tag->getAttributes();

        return new self(
            id: $tag->id,
            ledger_id: $tag->ledger_id,
            name: $tag->name,
            color: $tag->color,
            transactions_count: array_key_exists('transactions_count', $attributes)
                ? (int) $attributes['transactions_count']
                : null,
            created_at: $tag->created_at?->toIso8601String(),
            updated_at: $tag->updated_at?->toIso8601String(),
        );
    }
}
