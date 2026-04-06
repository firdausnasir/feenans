<?php

namespace App\Data\Transactions\Input;

use App\Data\Shared\Input\BaseInputData;
use App\Models\Attachment;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\LaravelData\Attributes\FromAuthenticatedUser;
use Spatie\LaravelData\Attributes\FromRouteParameter;

class DeleteTransactionAttachmentData extends BaseInputData
{
    public function __construct(
        #[FromRouteParameter('ledger')] public ?Ledger $ledger = null,
        #[FromRouteParameter('transaction')] public ?Transaction $transaction = null,
        #[FromRouteParameter('attachment')] public ?Attachment $attachment = null,
        #[FromAuthenticatedUser] public ?User $user = null,
    ) {}

    public static function authorize(Request $request): bool
    {
        $user = $request->user();
        $ledger = $request->route('ledger');

        return $user !== null
            && $ledger instanceof Ledger
            && $user->can('delete', $ledger);
    }
}
