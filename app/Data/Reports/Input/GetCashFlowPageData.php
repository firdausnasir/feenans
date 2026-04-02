<?php

namespace App\Data\Reports\Input;

use App\Data\Shared\Input\BaseInputData;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\FromAuthenticatedUser;
use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Normalizers\Normalizer;

class GetCashFlowPageData extends BaseInputData
{
    public function __construct(
        #[FromRouteParameter('ledger')] public Ledger $ledger,
        #[FromAuthenticatedUser] public User $user,
        public readonly ?string $date_from = null,
        public readonly ?string $date_to = null,
        public readonly ?string $account_id = null,
    ) {}

    public static function normalizers(): array
    {
        return [GetCashFlowPageDataNormalizer::class, ...parent::normalizers()];
    }

    public static function authorize(Request $request): bool
    {
        $user = $request->user();
        $ledger = $request->route('ledger');

        return $user !== null
            && $ledger instanceof Ledger
            && $user->can('view', $ledger);
    }

    public static function rules(): array
    {
        /** @var Ledger|null $ledger */
        $ledger = request()->route('ledger');

        $accountRule = $ledger instanceof Ledger
            ? Rule::exists('accounts', 'id')->where('ledger_id', $ledger->id)
            : 'exists:accounts,id';

        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'account_id' => ['nullable', 'string', $accountRule],
        ];
    }
}

class GetCashFlowPageDataNormalizer implements Normalizer
{
    public function normalize(mixed $value): ?array
    {
        if (! $value instanceof Request) {
            return null;
        }

        $payload = $value->all();

        foreach (['date_from', 'date_to', 'account_id'] as $field) {
            if (($payload[$field] ?? null) === '') {
                $payload[$field] = null;
            }
        }

        return $payload;
    }
}
