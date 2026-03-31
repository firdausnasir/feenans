<?php

namespace App\Actions\Auth\UseCases;

use App\Data\Auth\Input\CreateApiTokenData;
use App\Data\Auth\Output\ApiTokenData;

class CreateApiTokenAction
{
    public function __invoke(CreateApiTokenData $data): ApiTokenData
    {
        $token = $data->user->createToken($data->device_name, ['*']);

        return ApiTokenData::fromModel($token->accessToken, $token->plainTextToken);
    }
}
