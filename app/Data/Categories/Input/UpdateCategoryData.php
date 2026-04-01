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

class UpdateCategoryData extends BaseInputData
{
    public function __construct(
        #[FromRouteParameter('ledger')] public Ledger $ledger,
        #[FromRouteParameter('category')] public Category $category,
        #[FromAuthenticatedUser] public User $user,
        public ?string $name = null,
        public ?string $transaction_type = null,
        public ?string $color = null,
        public ?string $icon = null,
        public ?int $position = null,
        public ?int $parent_id = null,
    ) {}

    public static function normalizers(): array
    {
        return [UpdateCategoryRequestNormalizer::class, ...parent::normalizers()];
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

        /** @var Category|null $category */
        $category = request()->route('category');

        /** @var Category|null $parent */
        $parent = request()->integer('parent_id') !== null
            ? Category::query()->find(request()->integer('parent_id'))
            : null;

        $parentRule = $ledger instanceof Ledger
            ? Rule::exists('categories', 'id')->where('ledger_id', $ledger->id)
            : 'exists:categories,id';

        return [
            'name' => ['required', 'string', 'max:255'],
            'transaction_type' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['expense', 'income']),
                function (string $attribute, mixed $value, Closure $fail) use ($category): void {
                    if (! $category instanceof Category || ! is_string($value)) {
                        return;
                    }

                    $currentParent = $category->parent_id !== null
                        ? $category->parent()->first()
                        : null;

                    if (
                        $currentParent instanceof Category
                        && ! request()->exists('parent_id')
                        && $currentParent->transaction_type !== $value
                    ) {
                        $fail('The selected transaction type is invalid.');

                        return;
                    }

                    if ($category->children()->where('transaction_type', '!=', $value)->exists()) {
                        $fail('The selected transaction type is invalid.');
                    }
                },
            ],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'integer', 'min:0'],
            'parent_id' => [
                'nullable',
                'integer',
                $parentRule,
                function (string $attribute, mixed $value, Closure $fail) use ($category, $parent): void {
                    if (! $category instanceof Category) {
                        return;
                    }

                    if ($value !== null && $category->children()->exists()) {
                        $fail('The selected parent category is invalid.');

                        return;
                    }

                    if (! $parent instanceof Category) {
                        return;
                    }

                    if ($parent->id === $category->id) {
                        $fail('The selected parent category is invalid.');

                        return;
                    }

                    if ($parent->parent_id !== null) {
                        $fail('The selected parent category is invalid.');

                        return;
                    }

                    $transactionType = request()->exists('transaction_type')
                        ? request()->input('transaction_type')
                        : $category->transaction_type;

                    if ($parent->transaction_type !== $transactionType) {
                        $fail('The selected parent category is invalid.');
                    }
                },
            ],
        ];
    }

    public static function messages(): array
    {
        return [
            'transaction_type.in' => 'Please select a valid transaction type (expense or income).',
        ];
    }

    public static function attributes(): array
    {
        return [
            'transaction_type' => 'transaction type',
            'parent_id' => 'parent category',
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    public function attributesToUpdate(): array
    {
        $attributes = [];

        foreach (['name', 'transaction_type', 'color', 'icon', 'position', 'parent_id'] as $field) {
            if (request()->exists($field)) {
                $attributes[$field] = $this->{$field};
            }
        }

        return $attributes;
    }
}

class UpdateCategoryRequestNormalizer implements Normalizer
{
    public function normalize(mixed $value): ?array
    {
        if (! $value instanceof Request) {
            return null;
        }

        return $value->all();
    }
}
