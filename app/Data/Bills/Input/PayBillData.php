<?php

namespace App\Data\Bills\Input;

use App\Data\Shared\Input\BaseInputData;
use App\Models\Bill;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\FromAuthenticatedUser;
use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Normalizers\Normalizer;

class PayBillData extends BaseInputData
{
    public function __construct(
        #[FromRouteParameter('ledger')] public ?Ledger $ledger = null,
        #[FromRouteParameter('bill')] public ?Bill $bill = null,
        #[FromAuthenticatedUser] public ?User $user = null,
        public ?float $amount = null,
        public ?int $account_id = null,
        public ?int $to_account_id = null,
        public ?int $category_id = null,
        public ?int $payee_id = null,
        public ?string $date = null,
    ) {}

    public static function normalizers(): array
    {
        return [PayBillRequestNormalizer::class, ...parent::normalizers()];
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
        /** @var Ledger|null $ledger */
        $ledger = request()->route('ledger');

        $accountRule = $ledger instanceof Ledger
            ? Rule::exists('accounts', 'id')->where('ledger_id', $ledger->id)
            : 'exists:accounts,id';
        $categoryRule = $ledger instanceof Ledger
            ? Rule::exists('categories', 'id')->where('ledger_id', $ledger->id)
            : 'exists:categories,id';
        $payeeRule = $ledger instanceof Ledger
            ? Rule::exists('payees', 'id')->where('ledger_id', $ledger->id)
            : 'exists:payees,id';

        return [
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'account_id' => ['nullable', 'integer', $accountRule],
            'to_account_id' => ['nullable', 'integer', $accountRule, 'different:account_id'],
            'category_id' => ['nullable', 'integer', $categoryRule],
            'payee_id' => ['nullable', 'integer', $payeeRule],
            'date' => ['nullable', 'date'],
        ];
    }

    public static function messages(): array
    {
        return [
            'amount.min' => 'The amount must be at least 0.01.',
            'to_account_id.different' => 'The destination account must be different from the source account.',
        ];
    }

    public static function attributes(): array
    {
        return [
            'account_id' => 'account',
            'to_account_id' => 'destination account',
            'category_id' => 'category',
            'payee_id' => 'payee',
        ];
    }
}

class PayBillRequestNormalizer implements Normalizer
{
    public function normalize(mixed $value): ?array
    {
        if (! $value instanceof Request) {
            return null;
        }

        return $value->all();
    }
}
