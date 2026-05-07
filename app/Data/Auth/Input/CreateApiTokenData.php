<?php

namespace App\Data\Auth\Input;

use App\Data\Shared\Input\BaseInputData;
use App\Enums\ApiTokenAbility;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\FromAuthenticatedUser;
use Spatie\LaravelData\Normalizers\Normalizer;

class CreateApiTokenData extends BaseInputData
{
    /**
     * @param  list<string>|null  $abilities
     */
    public function __construct(
        public string $device_name,
        #[FromAuthenticatedUser] public User $user,
        public ?int $ledger_id = null,
        public ?array $abilities = null,
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
        $user = request()->user();

        return [
            'device_name' => ['required', 'string', 'max:255'],
            'ledger_id' => [
                'nullable',
                'integer',
                Rule::exists('ledgers', 'id')->where('user_id', $user?->id ?? 0),
            ],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => [
                'string',
                function (string $attribute, mixed $value, mixed $fail): void {
                    if (! is_string($value) || ! ApiTokenAbility::isValid($value)) {
                        $fail('The selected ability is invalid.');
                    }
                },
            ],
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
