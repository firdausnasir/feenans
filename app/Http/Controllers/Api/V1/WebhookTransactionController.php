<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Transactions\UseCases\ResolveTransactionWebhookPayloadAction;
use App\Enums\ApiTokenAbility;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionWebhookRequest;
use App\Jobs\ProcessTransactionWebhook;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Laravel\Sanctum\PersonalAccessToken;

class WebhookTransactionController extends Controller
{
    public function __invoke(
        StoreTransactionWebhookRequest $request,
        ResolveTransactionWebhookPayloadAction $resolvePayload,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        /** @var PersonalAccessToken $token */
        $token = $user->currentAccessToken();

        $payload = $resolvePayload(
            $user,
            $request->validated(),
            ApiTokenAbility::ledgerIdFromWebhookAbilities($token->abilities ?? []),
        );

        ProcessTransactionWebhook::dispatch($payload->toQueuePayload());

        return response()->json([
            'message' => 'Transaction webhook accepted for processing.',
        ], 202);
    }
}
