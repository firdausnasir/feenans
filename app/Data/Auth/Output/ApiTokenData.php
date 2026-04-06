<?php

namespace App\Data\Auth\Output;

use App\Data\Shared\Output\BaseOutputData;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenData extends BaseOutputData
{
    public function __construct(
        public int $id,
        public string $name,
        public array $abilities,
        public ?string $plain_text_token,
        public string $created_at,
        public ?string $last_used_at,
    ) {}

    public static function fromModel(PersonalAccessToken $token, ?string $plainTextToken = null): self
    {
        return new self(
            id: $token->id,
            name: $token->name,
            abilities: $token->abilities ?? [],
            plain_text_token: $plainTextToken,
            created_at: $token->created_at?->toAtomString() ?? now()->toAtomString(),
            last_used_at: $token->last_used_at?->toAtomString(),
        );
    }
}
