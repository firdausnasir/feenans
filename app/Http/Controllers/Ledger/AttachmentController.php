<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Ledger;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttachmentController extends Controller
{
    protected function ledgerDisk(): string
    {
        return (string) config('filesystems.ledger_disk', config('filesystems.default', 'local'));
    }

    public function store(Request $request, Ledger $ledger, Transaction $transaction): JsonResponse
    {
        $this->authorize('view', $ledger);

        $validated = $request->validate([
            'file' => [
                'required',
                File::types(['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'csv'])
                    ->max(10 * 1024),
            ],
        ]);

        $file = $validated['file'];
        $path = $file->store("attachments/{$ledger->id}", $this->ledgerDisk());

        $attachment = $transaction->attachments()->create([
            'filename' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
        ])->load('transaction');

        return response()->json([
            'attachment' => $attachment,
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, Ledger $ledger, Transaction $transaction, Attachment $attachment): BinaryFileResponse
    {
        $this->authorize('view', $ledger);

        return response()->file(Storage::disk($this->ledgerDisk())->path($attachment->path), [
            'Content-Type' => $attachment->mime_type,
        ]);
    }

    public function destroy(Request $request, Ledger $ledger, Transaction $transaction, Attachment $attachment): Response
    {
        $this->authorize('delete', $ledger);

        Storage::disk($this->ledgerDisk())->delete($attachment->path);
        $attachment->delete();

        return response()->noContent();
    }
}
