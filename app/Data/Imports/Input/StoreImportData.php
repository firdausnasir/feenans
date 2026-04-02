<?php

namespace App\Data\Imports\Input;

use App\Data\Shared\Input\BaseInputData;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\LaravelData\Attributes\FromAuthenticatedUser;
use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Normalizers\Normalizer;

class StoreImportData extends BaseInputData
{
    /**
     * @param  array<string, string>  $mapping
     */
    public function __construct(
        public int $account_id,
        public array $mapping,
        public ?bool $skip_duplicates,
        public ?string $file_path,
        public ?string $pending_import_handle,
        #[FromRouteParameter('ledger')] public Ledger $ledger,
        #[FromAuthenticatedUser] public User $user,
    ) {}

    public static function normalizers(): array
    {
        return [StoreImportRequestNormalizer::class, ...parent::normalizers()];
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

        $accountRule = $ledger instanceof Ledger
            ? Rule::exists('accounts', 'id')->where('ledger_id', $ledger->id)
            : 'exists:accounts,id';

        return [
            'file_path' => ['nullable', 'string', 'starts_with:imports/temp/'],
            'pending_import_handle' => ['nullable', 'string'],
            'account_id' => ['required', 'integer', $accountRule],
            'mapping' => ['required', 'array'],
            'mapping.date' => ['required', 'string'],
            'mapping.amount' => ['required', 'string'],
            'mapping.description' => ['nullable', 'string'],
            'mapping.category' => ['nullable', 'string'],
            'mapping.payee' => ['nullable', 'string'],
            'mapping.type' => ['nullable', 'string'],
            'skip_duplicates' => ['nullable', 'boolean'],
        ];
    }

    public static function messages(): array
    {
        return [
            'file_path.required' => 'Please upload a file to import.',
            'account_id.required' => 'Please select an account to import into.',
            'mapping.required' => 'Please configure the column mapping.',
            'mapping.date.required' => 'Please select which column contains the date.',
            'mapping.amount.required' => 'Please select which column contains the amount.',
        ];
    }

    public static function attributes(): array
    {
        return [
            'file_path' => 'file',
            'pending_import_handle' => 'pending import handle',
            'account_id' => 'account',
            'skip_duplicates' => 'skip duplicates',
            'mapping.date' => 'date column',
            'mapping.amount' => 'amount column',
            'mapping.description' => 'description column',
            'mapping.category' => 'category column',
            'mapping.payee' => 'payee column',
            'mapping.type' => 'type column',
        ];
    }

    public static function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (request()->routeIs('api.*')) {
                if (! is_string(request()->input('pending_import_handle')) || trim((string) request()->input('pending_import_handle')) === '') {
                    $validator->errors()->add('pending_import_handle', 'Import file not found. Please re-upload.');
                }

                return;
            }

            if ($validator->errors()->has('file_path')) {
                return;
            }

            /** @var Ledger $ledger */
            $ledger = request()->route('ledger');

            $filePath = request()->input('file_path');
            $expectedFilePath = request()->session()->get(self::pendingImportFilePathSessionKey($ledger));

            if (! is_string($filePath)
                || ! is_string($expectedFilePath)
                || $expectedFilePath === ''
                || ! str_starts_with($expectedFilePath, 'imports/temp/')
                || $filePath !== $expectedFilePath) {
                $validator->errors()->add('file_path', 'Import file not found. Please re-upload.');
            }
        });
    }

    private static function pendingImportFilePathSessionKey(Ledger $ledger): string
    {
        return "ledger-imports.{$ledger->id}.file_path";
    }
}

class StoreImportRequestNormalizer implements Normalizer
{
    public function normalize(mixed $value): ?array
    {
        if (! $value instanceof Request) {
            return null;
        }

        return $value->all();
    }
}
