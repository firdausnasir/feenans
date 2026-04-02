<?php

namespace App\Data\Budgets\Input;

use App\Data\Shared\Input\BaseInputData;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\FromAuthenticatedUser;
use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Normalizers\Normalizer;

class StoreBudgetData extends BaseInputData
{
    public function __construct(
        public ?int $category_id,
        public float $amount,
        public string $period,
        public string $start_date,
        public ?string $end_date,
        public bool $rollover,
        #[FromRouteParameter('ledger')] public Ledger $ledger,
        #[FromAuthenticatedUser] public User $user,
    ) {}

    public static function normalizers(): array
    {
        return [StoreBudgetRequestNormalizer::class, ...parent::normalizers()];
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

        return [
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('ledger_id', $ledger?->id)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'period' => ['required', 'string', Rule::in(['monthly', 'weekly', 'yearly'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'rollover' => ['boolean'],
        ];
    }

    public static function messages(): array
    {
        return [
            'amount.required' => 'Please enter a budget amount.',
            'amount.min' => 'The budget amount must be at least 0.01.',
            'period.required' => 'Please select a budget period.',
            'start_date.required' => 'Please select a start date.',
            'end_date.after' => 'The end date must be after the start date.',
        ];
    }

    public static function attributes(): array
    {
        return [
            'category_id' => 'category',
            'start_date' => 'start date',
            'end_date' => 'end date',
        ];
    }
}

class StoreBudgetRequestNormalizer implements Normalizer
{
    public function normalize(mixed $value): ?array
    {
        if (! $value instanceof Request) {
            return null;
        }

        return $value->all();
    }
}
