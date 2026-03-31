<?php

namespace App\Data\Auth\Input;

use App\Data\Shared\Input\BaseInputData;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\LaravelData\Attributes\FromAuthenticatedUser;
use Spatie\LaravelData\Normalizers\Normalizer;

class CreateApiTokenData extends BaseInputData
{
    public function __construct(
        public string $device_name,
        #[FromAuthenticatedUser] public User $user,
    ) {}

    public static function normalizers(): array
    {
        return [CreateApiTokenRequestNormalizer::class, ...parent::normalizers()];
    }

    public static function authorize(Request $request): bool
    {
        return $request->user() !== null;
    }

    public static function rules(): array
    {
        return [
            'device_name' => ['required', 'string', 'max:255'],
        ];
    }
}

class CreateApiTokenRequestNormalizer implements Normalizer
{
    public function normalize(mixed $value): ?array
    {
        if (! $value instanceof Request) {
            return null;
        }

        return $value->all();
    }
}
