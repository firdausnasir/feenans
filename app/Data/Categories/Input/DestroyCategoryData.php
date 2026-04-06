<?php

namespace App\Data\Categories\Input;

use App\Data\Shared\Input\BaseInputData;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\FromAuthenticatedUser;
use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Normalizers\Normalizer;

class DestroyCategoryData extends BaseInputData
{
    public function __construct(
        #[FromRouteParameter('ledger')] public Ledger $ledger,
        #[FromRouteParameter('category')] public Category $category,
        #[FromAuthenticatedUser] public User $user,
        public ?int $reassign_category_id = null,
    ) {}

    public static function normalizers(): array
    {
        return [DestroyCategoryRequestNormalizer::class, ...parent::normalizers()];
    }

    public static function authorize(Request $request): bool
    {
        $user = $request->user();
        $ledger = $request->route('ledger');

        return $user !== null
            && $ledger instanceof Ledger
            && $user->can('delete', $ledger);
    }

    public static function rules(): array
    {
        /** @var Ledger|null $ledger */
        $ledger = request()->route('ledger');

        /** @var Category|null $category */
        $category = request()->route('category');

        $excludedCategoryIds = $category instanceof Category
            ? $category->children()->pluck('id')->push($category->id)->all()
            : [];

        $reassignRule = $ledger instanceof Ledger && $category instanceof Category
            ? Rule::exists('categories', 'id')
                ->where('ledger_id', $ledger->id)
                ->whereNotIn('id', $excludedCategoryIds)
            : 'exists:categories,id';

        return [
            'reassign_category_id' => ['nullable', 'integer', $reassignRule],
        ];
    }

    public static function attributes(): array
    {
        return [
            'reassign_category_id' => 'reassignment category',
        ];
    }

    public function hasReassignmentInstruction(): bool
    {
        return request()->exists('reassign_category_id');
    }
}

class DestroyCategoryRequestNormalizer implements Normalizer
{
    public function normalize(mixed $value): ?array
    {
        if (! $value instanceof Request) {
            return null;
        }

        return $value->all();
    }
}
