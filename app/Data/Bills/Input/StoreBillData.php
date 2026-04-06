<?php

namespace App\Data\Bills\Input;

use App\Data\Shared\Input\BaseInputData;
use App\Enums\RecurrenceType;
use App\Enums\TransactionType;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\FromAuthenticatedUser;
use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Normalizers\Normalizer;

class StoreBillData extends BaseInputData
{
    public function __construct(
        public string $name,
        public string $transaction_type,
        public float $amount,
        public int $account_id,
        public string $recurrence_type,
        public int $recurrence_interval,
        public ?int $to_account_id = null,
        public ?int $category_id = null,
        public ?int $payee_id = null,
        public ?string $new_payee_name = null,
        public ?int $recurrence_day = null,
        public ?string $next_due_date = null,
        public bool $auto_create = false,
        public bool $is_active = true,
        public bool $notify_email = true,
        public ?string $end_type = null,
        public ?string $end_date = null,
        public ?int $end_after_occurrences = null,
        #[FromRouteParameter('ledger')] public ?Ledger $ledger = null,
        #[FromAuthenticatedUser] public ?User $user = null,
    ) {}

    public static function normalizers(): array
    {
        return [StoreBillRequestNormalizer::class, ...parent::normalizers()];
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
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'transaction_type' => ['required', 'string', Rule::in([
                TransactionType::Expense->value,
                TransactionType::Income->value,
                TransactionType::Transfer->value,
            ])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'account_id' => ['required', 'integer', $accountRule],
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
            'recurrence_type' => ['required', 'string', Rule::in(array_column(RecurrenceType::cases(), 'value'))],
            'recurrence_interval' => ['required', 'integer', 'min:1'],
            'recurrence_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'next_due_date' => ['required', 'date'],
            'auto_create' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'notify_email' => ['nullable', 'boolean'],
            'end_type' => ['nullable', 'string', Rule::in(['never', 'on_date', 'after_occurrences'])],
            'end_date' => ['nullable', 'date', 'required_if:end_type,on_date'],
            'end_after_occurrences' => ['nullable', 'integer', 'min:1', 'required_if:end_type,after_occurrences'],
        ];
    }

    public static function messages(): array
    {
        return [
            'account_id.required' => 'Please select an account.',
            'to_account_id.required' => 'Please select a destination account for this transfer.',
            'to_account_id.different' => 'The destination account must be different from the source account.',
            'transaction_type.required' => 'Please select a transaction type.',
            'amount.required' => 'Please enter an amount.',
            'amount.min' => 'The amount must be at least 0.01.',
            'recurrence_type.required' => 'Please select how often this bill recurs.',
            'recurrence_interval.required' => 'Please enter a recurrence interval.',
            'recurrence_interval.min' => 'The recurrence interval must be at least 1.',
            'next_due_date.required' => 'Please select the next due date.',
            'next_due_date.date' => 'Please enter a valid due date.',
            'end_date.required_if' => 'Please select an end date.',
            'end_after_occurrences.required_if' => 'Please enter the number of occurrences.',
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
}

class StoreBillRequestNormalizer implements Normalizer
{
    public function normalize(mixed $value): ?array
    {
        if (! $value instanceof Request) {
            return null;
        }

        return $value->all();
    }
}
