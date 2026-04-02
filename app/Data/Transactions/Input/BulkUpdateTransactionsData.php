<?php

namespace App\Data\Transactions\Input;

use App\Data\Shared\Input\BaseInputData;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\FromAuthenticatedUser;
use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Normalizers\Normalizer;

class BulkUpdateTransactionsData extends BaseInputData
{
    /**
     * @param  array<int, int>|null  $ids
     * @param  array<int, int>|null  $excluded_ids
     * @param  array<string, mixed>|null  $filters
     */
    public function __construct(
        public string $action,
        public int $value,
        public ?bool $apply_to_all_matching,
        public ?array $ids,
        public ?array $excluded_ids,
        public ?array $filters,
        #[FromRouteParameter('ledger')] public Ledger $ledger,
        #[FromAuthenticatedUser] public User $user,
    ) {}

    public static function normalizers(): array
    {
        return [BulkUpdateTransactionsRequestNormalizer::class, ...parent::normalizers()];
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

        $categoryRule = $ledger instanceof Ledger
            ? Rule::exists('categories', 'id')->where('ledger_id', $ledger->id)
            : 'exists:categories,id';
        $accountRule = $ledger instanceof Ledger
            ? Rule::exists('accounts', 'id')->where('ledger_id', $ledger->id)
            : 'exists:accounts,id';
        $payeeRule = $ledger instanceof Ledger
            ? Rule::exists('payees', 'id')->where('ledger_id', $ledger->id)
            : 'exists:payees,id';

        return [
            'apply_to_all_matching' => ['sometimes', 'boolean'],
            'ids' => ['required_without:apply_to_all_matching', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
            'excluded_ids' => ['nullable', 'array'],
            'excluded_ids.*' => ['integer'],
            'filters' => ['nullable', 'array'],
            'action' => ['required', 'string', Rule::in(['change_category', 'change_account', 'change_payee'])],
            'value' => [
                'required',
                'integer',
                match (request()->input('action')) {
                    'change_category' => $categoryRule,
                    'change_account' => $accountRule,
                    'change_payee' => $payeeRule,
                    default => 'integer',
                },
            ],
        ];
    }

    public static function messages(): array
    {
        return [
            'ids.required' => 'Please select at least one transaction.',
            'ids.required_without' => 'Please select at least one transaction.',
            'ids.min' => 'Please select at least one transaction.',
            'action.required' => 'Please select an action to perform.',
            'action.in' => 'Please select a valid action.',
            'value.required' => 'Please select a value for the action.',
            'value.exists' => 'The selected value does not belong to this ledger.',
        ];
    }
}

class BulkUpdateTransactionsRequestNormalizer implements Normalizer
{
    public function normalize(mixed $value): ?array
    {
        if (! $value instanceof Request) {
            return null;
        }

        return $value->all();
    }
}
