<?php

namespace App\Data\Transactions\Input;

use App\Data\Shared\Input\BaseInputData;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\LaravelData\Attributes\FromAuthenticatedUser;
use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Normalizers\Normalizer;

class BulkDestroyTransactionsData extends BaseInputData
{
    /**
     * @param  array<int, int>|null  $ids
     * @param  array<int, int>|null  $excluded_ids
     * @param  array<string, mixed>|null  $filters
     */
    public function __construct(
        public ?bool $apply_to_all_matching,
        public ?array $ids,
        public ?array $excluded_ids,
        public ?array $filters,
        #[FromRouteParameter('ledger')] public Ledger $ledger,
        #[FromAuthenticatedUser] public User $user,
    ) {}

    public static function normalizers(): array
    {
        return [BulkDestroyTransactionsRequestNormalizer::class, ...parent::normalizers()];
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
        return [
            'apply_to_all_matching' => ['sometimes', 'boolean'],
            'ids' => ['required_without:apply_to_all_matching', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
            'excluded_ids' => ['nullable', 'array'],
            'excluded_ids.*' => ['integer'],
            'filters' => ['nullable', 'array'],
        ];
    }

    public static function messages(): array
    {
        return [
            'ids.required_without' => 'Please select at least one transaction.',
            'ids.min' => 'Please select at least one transaction.',
        ];
    }
}

class BulkDestroyTransactionsRequestNormalizer implements Normalizer
{
    public function normalize(mixed $value): ?array
    {
        if (! $value instanceof Request) {
            return null;
        }

        return $value->all();
    }
}
