<?php

namespace App\Data\Categories\Input;

use App\Data\Shared\Input\BaseInputData;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\FromAuthenticatedUser;
use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Normalizers\Normalizer;

class StoreCategoryData extends BaseInputData
{
    public function __construct(
        public string $name,
        public string $transaction_type,
        public ?string $color,
        public ?string $icon,
        public ?int $parent_id,
        #[FromRouteParameter('ledger')] public Ledger $ledger,
        #[FromAuthenticatedUser] public User $user,
    ) {}

    public static function normalizers(): array
    {
        return [StoreCategoryRequestNormalizer::class, ...parent::normalizers()];
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

        /** @var Category|null $parent */
        $parent = request()->integer('parent_id') !== null
            ? Category::query()->find(request()->integer('parent_id'))
            : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'transaction_type' => ['required', 'string', Rule::in(['expense', 'income'])],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:50'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('ledger_id', $ledger?->id),
                function (string $attribute, mixed $value, Closure $fail) use ($parent): void {
                    if (! $parent instanceof Category) {
                        return;
                    }

                    if ($parent->parent_id !== null) {
                        $fail('The selected parent category is invalid.');

                        return;
                    }

                    if ($parent->transaction_type !== request()->input('transaction_type')) {
                        $fail('The selected parent category is invalid.');
                    }
                },
            ],
        ];
    }

    public static function messages(): array
    {
        return [
            'transaction_type.required' => 'Please select a transaction type.',
            'transaction_type.in' => 'Please select a valid transaction type (expense or income).',
        ];
    }

    public static function attributes(): array
    {
        return [
            'transaction_type' => 'transaction type',
        ];
    }
}

class StoreCategoryRequestNormalizer implements Normalizer
{
    public function normalize(mixed $value): ?array
    {
        if (! $value instanceof Request) {
            return null;
        }

        return $value->all();
    }
}
