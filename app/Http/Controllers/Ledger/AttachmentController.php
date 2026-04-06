<?php

namespace App\Http\Controllers\Ledger;

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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Uri;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function index(Ledger $ledger, Transaction $transaction): JsonResponse
    {
        $this->authorize('view', $ledger);

        $attachments = $transaction->attachments()->with('transaction')->get();

        return response()->json([
            'attachments' => AttachmentResource::collection($attachments)->resolve(),
        ]);
    }

    public function store(
        Ledger $ledger,
        Transaction $transaction,
        StoreTransactionAttachmentsData $data,
        StoreTransactionAttachmentsAction $storeTransactionAttachments,
    ): RedirectResponse {
        $uploaded = ($storeTransactionAttachments)($ledger, $transaction, $data->attachments ?? []);

        return redirect()
            ->to($this->redirectUrl($ledger, $transaction))
            ->with('success', count($uploaded) === 1 ? 'Attachment uploaded.' : 'Attachments uploaded.')
            ->with('attachment_uploads', AttachmentResource::collection($uploaded)->resolve());
    }

    public function show(Request $request, Ledger $ledger, Transaction $transaction, Attachment $attachment): StreamedResponse
    {
        $this->authorize('view', $ledger);

        return Storage::response($attachment->path, $attachment->filename, [
            'Content-Type' => $attachment->mime_type,
        ]);
    }

    public function destroy(
        Ledger $ledger,
        Transaction $transaction,
        Attachment $attachment,
        DeleteTransactionAttachmentData $data,
        DeleteTransactionAttachmentAction $deleteTransactionAttachment,
    ): RedirectResponse {
        $attachment = $deleteTransactionAttachment($data->attachment);

        return redirect()
            ->to($this->redirectUrl($ledger, $transaction))
            ->with('success', 'Attachment deleted.')
            ->with('deleted_attachment_id', $attachment->id);
    }

    private function redirectUrl(Ledger $ledger, Transaction $transaction): string
    {
        $fallback = route('ledgers.transactions.edit', [$ledger, $transaction]);
        $request = request();
        $referer = $request->headers->get('referer');

        if (! is_string($referer) || $referer === '') {
            return $fallback;
        }

        try {
            $refererUri = Uri::of($referer);
        } catch (\Throwable) {
            return $fallback;
        }

        $currentOrigin = Uri::of($request->root());

        if ($refererUri->scheme() !== $currentOrigin->scheme() || $refererUri->authority() !== $currentOrigin->authority()) {
            return $fallback;
        }

        return (string) $refererUri;
    }
}
