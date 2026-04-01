<?php

namespace App\Data\Accounts\Input;

use App\Data\Shared\Input\BaseInputData;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\LaravelData\Attributes\FromAuthenticatedUser;
use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Normalizers\Normalizer;

class AdjustAccountBalanceData extends BaseInputData
{
    public function __construct(
        public float $amount,
        #[FromRouteParameter('ledger')] public Ledger $ledger,
        #[FromRouteParameter('account')] public Account $account,
        #[FromAuthenticatedUser] public User $user,
        public ?string $date = null,
        public ?string $description = null,
    ) {}

    public static function normalizers(): array
    {
        return [AdjustAccountBalanceRequestNormalizer::class, ...parent::normalizers()];
    }

    public static function authorize(Request $request): bool
    {
        $user = $request->user();
        $ledger = $request->route('ledger');

        return $user !== null
            && $ledger instanceof Ledger
            && $user->can('update', $ledger);
    }

    public static function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'not_in:0'],
            'date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public static function messages(): array
    {
        return [
            'amount.required' => 'Please provide an adjustment amount.',
            'amount.not_in' => 'The adjustment amount cannot be zero.',
        ];
    }
}

class AdjustAccountBalanceRequestNormalizer implements Normalizer
{
    public function normalize(mixed $value): ?array
    {
        if (! $value instanceof Request) {
            return null;
        }

        return $value->all();
    }
}
