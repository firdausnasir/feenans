<?php

namespace App\Data\Transactions\Input;

use App\Data\Shared\Input\BaseInputData;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\LaravelData\Attributes\FromAuthenticatedUser;
use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Normalizers\Normalizer;

class UpdateTransactionData extends BaseInputData
{
    /**
     * @param  array<int, array{amount:mixed,category_id:mixed,description:mixed,payee_id?:mixed}>|null  $splits
     * @param  array<int, int>|null  $tag_ids
     */
    public function __construct(
        public int $account_id,
        public string $transaction_type,
        public float $amount,
        public string $transaction_date,
        public ?int $to_account_id = null,
        public ?int $category_id = null,
        public ?int $payee_id = null,
        public ?string $new_payee_name = null,
        public ?string $description = null,
        public ?string $notes = null,
        public ?array $splits = null,
        public ?array $tag_ids = null,
        #[FromRouteParameter('ledger')] public ?Ledger $ledger = null,
        #[FromRouteParameter('transaction')] public ?Transaction $transaction = null,
        #[FromAuthenticatedUser] public ?User $user = null,
    ) {}

    public static function normalizers(): array
    {
        return [UpdateTransactionRequestNormalizer::class, ...parent::normalizers()];
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
        $tagRule = $ledger instanceof Ledger
            ? Rule::exists('tags', 'id')->where('ledger_id', $ledger->id)
            : 'exists:tags,id';

        return [
            'account_id' => ['required', 'integer', $accountRule],
            'to_account_id' => [
                'nullable',
                'integer',
                $accountRule,
                'different:account_id',
                Rule::requiredIf(request()->input('transaction_type') === 'transfer'),
            ],
            'category_id' => ['nullable', 'integer', $categoryRule],
            'payee_id' => ['nullable', 'integer', $payeeRule],
            'new_payee_name' => ['nullable', 'string', 'max:255'],
            'transaction_type' => ['required', 'string', Rule::in(['expense', 'income', 'transfer'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'transaction_date' => ['required', 'date'],
            'splits' => ['nullable', 'array', 'min:2'],
            'splits.*.amount' => ['required', 'numeric', 'gt:0'],
            'splits.*.category_id' => ['nullable', 'integer', $categoryRule],
            'splits.*.description' => ['nullable', 'string', 'max:255'],
            'splits.*.payee_id' => ['nullable', 'integer', $payeeRule],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', $tagRule],
        ];
    }

    public static function messages(): array
    {
        return [
            'account_id.required' => 'Please select an account.',
            'to_account_id.required' => 'Please select a destination account for this transfer.',
            'to_account_id.different' => 'The destination account must be different from the source account.',
            'transaction_type.required' => 'Please select a transaction type.',
            'transaction_type.in' => 'Please select a valid transaction type (expense, income, or transfer).',
            'amount.required' => 'Please enter an amount.',
            'amount.numeric' => 'Please enter a valid amount.',
            'amount.gt' => 'Please enter an amount greater than zero.',
            'transaction_date.required' => 'Please select a date.',
            'transaction_date.date' => 'Please enter a valid date.',
            'splits.min' => 'A split transaction must have at least two splits.',
            'splits.*.amount.required' => 'Please enter an amount for each split.',
            'splits.*.amount.gt' => 'Please enter an amount greater than zero.',
        ];
    }

    public static function attributes(): array
    {
        return [
            'account_id' => 'account',
            'to_account_id' => 'destination account',
            'category_id' => 'category',
            'payee_id' => 'payee',
            'transaction_type' => 'transaction type',
            'transaction_date' => 'date',
            'tag_ids' => 'tags',
            'splits.*.amount' => 'split amount',
            'splits.*.category_id' => 'split category',
        ];
    }

    public static function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $splits = request()->input('splits');

            if (! is_array($splits) || $splits === []) {
                return;
            }

            $total = array_reduce($splits, function (float $carry, mixed $split): float {
                if (! is_array($split)) {
                    return $carry;
                }

                return $carry + (float) ($split['amount'] ?? 0);
            }, 0.0);

            if (round($total, 2) !== round((float) request()->input('amount'), 2)) {
                $validator->errors()->add('splits', 'Split amounts must equal the transaction total.');
            }
        });
    }
}

class UpdateTransactionRequestNormalizer implements Normalizer
{
    public function normalize(mixed $value): ?array
    {
        if (! $value instanceof Request) {
            return null;
        }

        return $value->all();
    }
}
