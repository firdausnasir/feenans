<?php

namespace App\Data\Transactions\Input;

use App\Data\Shared\Input\BaseInputData;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\FromAuthenticatedUser;
use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Normalizers\Normalizer;

class StoreTransactionAttachmentsData extends BaseInputData
{
    /**
     * @param  array<int, UploadedFile>|null  $attachments
     */
    public function __construct(
        public ?array $attachments = null,
        #[FromRouteParameter('ledger')] public ?Ledger $ledger = null,
        #[FromRouteParameter('transaction')] public ?Transaction $transaction = null,
        #[FromAuthenticatedUser] public ?User $user = null,
    ) {}

    public static function normalizers(): array
    {
        return [StoreTransactionAttachmentsRequestNormalizer::class, ...parent::normalizers()];
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
            'attachments' => ['required', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,gif,webp'],
        ];
    }

    public static function messages(): array
    {
        return [
            'attachments.required' => 'Please choose at least one attachment.',
            'attachments.array' => 'Attachments must be uploaded as a list of files.',
            'attachments.max' => 'You may upload up to 10 attachments at a time.',
            'attachments.*.file' => 'Each attachment must be a valid file.',
            'attachments.*.max' => 'Each attachment must not be larger than 5 MB.',
            'attachments.*.mimes' => 'Attachments must be a PDF or image file.',
        ];
    }
}

class StoreTransactionAttachmentsRequestNormalizer implements Normalizer
{
    public function normalize(mixed $value): ?array
    {
        if (! $value instanceof Request) {
            return null;
        }

        $payload = $value->all();

        if ($value->hasFile('file') && ! $value->hasFile('attachments')) {
            $payload['attachments'] = [$value->file('file')];
        }

        return $payload;
    }
}
