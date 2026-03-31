<?php

namespace App\Actions\Auth\UseCases;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class RevokeApiTokenAction
{
    public function __invoke(User $user, ?int $tokenId = null, ?PersonalAccessToken $currentToken = null): void
    {
        if ($tokenId !== null) {
            $user->tokens()->whereKey($tokenId)->delete();

            return;
        }

        if ($currentToken !== null) {
            $currentToken->delete();
        }
    }
}
