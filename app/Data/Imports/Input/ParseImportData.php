<?php

namespace App\Data\Imports\Input;

use App\Data\Shared\Input\BaseInputData;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\FromAuthenticatedUser;
use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Normalizers\Normalizer;

class ParseImportData extends BaseInputData
{
    public function __construct(
        public UploadedFile $file,
        #[FromRouteParameter('ledger')] public Ledger $ledger,
        #[FromAuthenticatedUser] public User $user,
    ) {}

    public static function normalizers(): array
    {
        return [ParseImportRequestNormalizer::class, ...parent::normalizers()];
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
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ];
    }

    public static function messages(): array
    {
        return [
            'file.required' => 'Please select a file to upload.',
            'file.mimes' => 'The file must be a CSV or text file.',
            'file.max' => 'The file must not be larger than 5 MB.',
        ];
    }
}

class ParseImportRequestNormalizer implements Normalizer
{
    public function normalize(mixed $value): ?array
    {
        if (! $value instanceof Request) {
            return null;
        }

        $payload = $value->all();
        $payload['file'] = $value->file('file');

        return $payload;
    }
}
