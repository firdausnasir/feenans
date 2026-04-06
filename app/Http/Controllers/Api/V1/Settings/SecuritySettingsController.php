<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Features;

class SecuritySettingsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $canManage = Features::canManageTwoFactorAuthentication();

        return response()->json([
            'data' => [
                'canManageTwoFactor' => $canManage,
                'requiresConfirmation' => $canManage
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
                'twoFactorEnabled' => $canManage
                    && $user->hasEnabledTwoFactorAuthentication(),
            ],
        ]);
    }
}
