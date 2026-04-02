<?php

namespace App\Data\Accounts\Input;

use App\Data\Shared\Input\BaseInputData;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\FromAuthenticatedUser;
use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Normalizers\Normalizer;

class StoreAccountData extends BaseInputData
{
    public function __construct(
        public int $account_type_id,
        public string $name,
        public float $initial_balance,
        public bool $include_in_totals,
        public ?string $color = null,
        public ?int $statement_day = null,
        public ?int $payment_due_day = null,
        #[FromRouteParameter('ledger')] public ?Ledger $ledger = null,
        #[FromAuthenticatedUser] public ?User $user = null,
    ) {}

    public static function normalizers(): array
    {
        return [StoreAccountRequestNormalizer::class, ...parent::normalizers()];
    }

    public static function authorize(Request $request): bool
    {
        $user = $request->user();
        $ledger = $request->route('ledger');

        if ($user === null || ! $ledger instanceof Ledger || ! $user->can('update', $ledger)) {
            return false;
        }

        if ($user->isPremium()) {
            return true;
        }

        return $ledger->accounts()->count() < 7;
    }

    public static function rules(): array
    {
        /** @var Ledger|null $ledger */
        $ledger = request()->route('ledger');

        $accountTypeRule = $ledger instanceof Ledger
            ? Rule::exists('account_types', 'id')->where('ledger_id', $ledger->id)
            : 'exists:account_types,id';

        return [
            'account_type_id' => ['required', 'integer', $accountTypeRule],
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:7'],
            'initial_balance' => ['required', 'numeric'],
            'statement_day' => ['nullable', 'integer', 'between:1,31'],
            'payment_due_day' => ['nullable', 'integer', 'between:1,31'],
            'include_in_totals' => ['required', 'boolean'],
        ];
    }

    public static function messages(): array
    {
        return [
            'account_type_id.required' => 'Please select an account type.',
            'initial_balance.required' => 'Please enter an initial balance.',
            'initial_balance.numeric' => 'Please enter a valid initial balance.',
            'statement_day.between' => 'The statement day must be between 1 and 31.',
            'include_in_totals.required' => 'Please specify whether to include this account in totals.',
        ];
    }

    public static function attributes(): array
    {
        return [
            'account_type_id' => 'account type',
            'initial_balance' => 'initial balance',
            'statement_day' => 'statement day',
            'include_in_totals' => 'include in totals',
        ];
    }
}

class StoreAccountRequestNormalizer implements Normalizer
{
    public function normalize(mixed $value): ?array
    {
        if (! $value instanceof Request) {
            return null;
        }

        return $value->all();
    }
}
