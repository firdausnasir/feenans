<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\UseCases\CreateApiTokenAction;
use App\Actions\Auth\UseCases\RevokeApiTokenAction;
use App\Data\Auth\Input\CreateApiTokenData;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenController extends Controller
{
    public function store(CreateApiTokenData $data, CreateApiTokenAction $createApiToken): JsonResponse
    {
        return response()->json([
            'data' => $createApiToken($data)->toArray(),
        ], 201);
    }

    public function destroy(Request $request, int $token, RevokeApiTokenAction $revokeApiToken): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $revokeApiToken($user, tokenId: $token);

        return response()->json(status: 204);
    }

    public function destroyCurrent(Request $request, RevokeApiTokenAction $revokeApiToken): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $currentToken = $user->currentAccessToken();

        if (! $currentToken instanceof PersonalAccessToken) {
            throw ValidationException::withMessages([
                'token' => 'No current access token is available for this request.',
            ]);
        }

        $revokeApiToken(
            $user,
            currentToken: $currentToken,
        );

        return response()->json(status: 204);
    }
}
