<?php

namespace App\Data\Imports\Input;

use App\Data\Shared\Input\BaseInputData;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\LaravelData\Attributes\FromAuthenticatedUser;
use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Normalizers\Normalizer;

class StoreImportMappingData extends BaseInputData
{
    /**
     * @param  array<string, string>  $mapping
     */
    public function __construct(
        public string $name,
        public array $mapping,
        #[FromRouteParameter('ledger')] public Ledger $ledger,
        #[FromAuthenticatedUser] public User $user,
    ) {}

    public static function normalizers(): array
    {
        return [StoreImportMappingRequestNormalizer::class, ...parent::normalizers()];
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'mapping' => ['required', 'array'],
        ];
    }

    public static function messages(): array
    {
        return [
            'name.required' => 'Please provide a name for this mapping.',
            'mapping.required' => 'Please provide the mapping configuration.',
        ];
    }
}

class StoreImportMappingRequestNormalizer implements Normalizer
{
    public function normalize(mixed $value): ?array
    {
        if (! $value instanceof Request) {
            return null;
        }

        return $value->all();
    }
}
