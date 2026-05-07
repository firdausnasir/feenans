<?php

namespace App\Actions\Auth\UseCases;

use App\Data\Auth\Input\CreateApiTokenData;
use App\Data\Auth\Output\ApiTokenData;
use App\Enums\ApiTokenAbility;

class CreateApiTokenAction
{
    public function __invoke(CreateApiTokenData $data): ApiTokenData
    {
        $abilities = $data->abilities;

        if ($data->ledger_id !== null) {
            $abilities = [ApiTokenAbility::transactionWebhookForLedger($data->ledger_id)];
        } elseif ($abilities === null || $abilities === []) {
            $abilities = [ApiTokenAbility::All->value];
        }

        $token = $data->user->createToken($data->device_name, $abilities);

        return ApiTokenData::fromModel($token->accessToken, $token->plainTextToken);
    }
}
