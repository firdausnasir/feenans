<?php

namespace App\Data\Bills\Input;

use App\Data\Shared\Input\BaseInputData;
use App\Enums\RecurrenceType;
use App\Enums\TransactionType;
use App\Models\Bill;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\FromAuthenticatedUser;
use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Normalizers\Normalizer;

class UpdateBillData extends BaseInputData
{
    public function __construct(
        #[FromRouteParameter('ledger')] public ?Ledger $ledger = null,
        #[FromRouteParameter('bill')] public ?Bill $bill = null,
        #[FromAuthenticatedUser] public ?User $user = null,
        public ?string $name = null,
        public ?string $transaction_type = null,
        public ?float $amount = null,
        public ?int $account_id = null,
        public ?int $to_account_id = null,
        public ?int $category_id = null,
        public ?int $payee_id = null,
        public ?string $new_payee_name = null,
        public ?string $recurrence_type = null,
        public ?int $recurrence_interval = null,
        public ?int $recurrence_day = null,
        public ?string $next_due_date = null,
        public ?bool $auto_create = null,
        public ?string $end_type = null,
        public ?string $end_date = null,
        public ?int $end_after_occurrences = null,
    ) {}

    public static function normalizers(): array
    {
        return [UpdateBillRequestNormalizer::class, ...parent::normalizers()];
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
            'name' => ['sometimes', 'string', 'min:2', 'max:255'],
            'transaction_type' => ['sometimes', 'string', Rule::in([
                TransactionType::Expense->value,
                TransactionType::Income->value,
                TransactionType::Transfer->value,
            ])],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'account_id' => ['sometimes', 'integer', $accountRule],
            'to_account_id' => [
                'nullable',
                'integer',
                $accountRule,
                'different:account_id',
                Rule::requiredIf(request()->input('transaction_type') === TransactionType::Transfer->value),
            ],
            'category_id' => [
                'nullable',
                'integer',
                $categoryRule,
                Rule::prohibitedIf(request()->input('transaction_type') === TransactionType::Transfer->value),
            ],
            'payee_id' => [
                'nullable',
                'integer',
                $payeeRule,
                Rule::prohibitedIf(request()->input('transaction_type') === TransactionType::Transfer->value),
            ],
            'new_payee_name' => ['nullable', 'string', 'max:255'],
            'recurrence_type' => ['sometimes', 'string', Rule::in(array_column(RecurrenceType::cases(), 'value'))],
            'recurrence_interval' => ['sometimes', 'integer', 'min:1'],
            'recurrence_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'next_due_date' => ['sometimes', 'date'],
            'auto_create' => ['nullable', 'boolean'],
            'end_type' => ['nullable', 'string', Rule::in(['never', 'on_date', 'after_occurrences'])],
            'end_date' => ['nullable', 'date', 'required_if:end_type,on_date'],
            'end_after_occurrences' => ['nullable', 'integer', 'min:1', 'required_if:end_type,after_occurrences'],
        ];
    }

    public static function messages(): array
    {
        return [
            'to_account_id.different' => 'The destination account must be different from the source account.',
            'amount.min' => 'The amount must be at least 0.01.',
            'recurrence_interval.min' => 'The recurrence interval must be at least 1.',
            'next_due_date.date' => 'Please enter a valid due date.',
            'end_date.required_if' => 'Please select an end date.',
            'end_after_occurrences.required_if' => 'Please enter the number of occurrences.',
            'to_account_id.required' => 'Please select a destination account for this transfer.',
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
            'recurrence_type' => 'recurrence type',
            'recurrence_interval' => 'recurrence interval',
            'recurrence_day' => 'recurrence day',
            'next_due_date' => 'next due date',
            'auto_create' => 'auto-create',
            'end_type' => 'end type',
            'end_date' => 'end date',
            'end_after_occurrences' => 'number of occurrences',
        ];
    }

    /**
     * @return array<string, bool|float|int|string|null>
     */
    public function attributesToUpdate(): array
    {
        $attributes = [];

        foreach ([
            'name',
            'transaction_type',
            'amount',
            'account_id',
            'to_account_id',
            'category_id',
            'payee_id',
            'recurrence_type',
            'recurrence_interval',
            'recurrence_day',
            'next_due_date',
            'auto_create',
            'end_type',
            'end_date',
            'end_after_occurrences',
        ] as $field) {
            if (request()->exists($field)) {
                $attributes[$field] = $this->{$field};
            }
        }

        return $attributes;
    }
}

class UpdateBillRequestNormalizer implements Normalizer
{
    public function normalize(mixed $value): ?array
    {
        if (! $value instanceof Request) {
            return null;
        }

        return $value->all();
    }
}
