<?php

namespace App\Data\Categories\Output;

use App\Data\Shared\Output\BaseOutputData;
use App\Models\Category;
use Spatie\LaravelData\Optional;

class CategoryData extends BaseOutputData
{
    /**
     * @param  array<int, array<string, mixed>>  $children
     */
    public function __construct(
        public int $id,
        public int $ledger_id,
        public ?int $parent_id,
        public string $name,
        public ?string $transaction_type,
        public ?string $color,
        public ?string $icon,
        public ?int $position,
        public ?int $transactions_count,
        public array|Optional $children,
        public ?string $created_at,
        public ?string $updated_at,
    ) {}

    public static function fromModel(Category $category): self
    {
        $attributes = $category->getAttributes();

        return new self(
            id: $category->id,
            ledger_id: $category->ledger_id,
            parent_id: $category->parent_id,
            name: $category->name,
            transaction_type: $category->transaction_type,
            color: $category->color,
            icon: $category->icon,
            position: $category->position,
            transactions_count: array_key_exists('transactions_count', $attributes)
                ? (int) $attributes['transactions_count']
                : null,
            children: $category->relationLoaded('children')
                ? $category->children->map(fn (Category $child) => self::fromModel($child)->toArray())->values()->all()
                : Optional::create(),
            created_at: $category->created_at?->toIso8601String(),
            updated_at: $category->updated_at?->toIso8601String(),
        );
    }
}
