<?php

namespace App\Http\Controllers\Api\V1\Ledger;

use App\Actions\Transactions\UseCases\DeleteTransactionAttachmentAction;
use App\Actions\Transactions\UseCases\StoreTransactionAttachmentsAction;
use App\Data\Transactions\Input\DeleteTransactionAttachmentData;
use App\Data\Transactions\Input\StoreTransactionAttachmentsData;
use App\Http\Controllers\Controller;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\Ledger;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;

class TransactionAttachmentController extends Controller
{
    public function index(Ledger $ledger, Transaction $transaction): JsonResponse
    {
        $this->authorize('view', $ledger);

        return response()->json([
            'data' => AttachmentResource::collection($transaction->attachments()->with('transaction')->get())->resolve(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function store(
        Ledger $ledger,
        Transaction $transaction,
        StoreTransactionAttachmentsData $data,
        StoreTransactionAttachmentsAction $storeTransactionAttachments,
    ): JsonResponse {
        return response()->json([
            'data' => AttachmentResource::collection(
                $storeTransactionAttachments($ledger, $transaction, $data->attachments ?? [])
            )->resolve(),
        ], 201, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function destroy(
        Ledger $ledger,
        Transaction $transaction,
        Attachment $attachment,
        DeleteTransactionAttachmentData $data,
        DeleteTransactionAttachmentAction $deleteTransactionAttachment,
    ): JsonResponse {
        return response()->json([
            'data' => AttachmentResource::make($deleteTransactionAttachment($data->attachment))->resolve(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}
