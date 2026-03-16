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
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    protected function attachmentDisk(): string
    {
        return (string) config('app.attachment_disk', 'local');
    }

    public function store(Request $request, Ledger $ledger, Transaction $transaction): JsonResponse
    {
        $this->authorize('view', $ledger);

        // Support both single 'file' and multiple 'attachments[]' uploads
        if ($request->hasFile('file') && ! $request->hasFile('attachments')) {
            $request->merge(['attachments' => [$request->file('file')]]);
        }

        $validated = $request->validate([
            'attachments' => ['required', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,gif,webp'],
        ]);

        $disk = $this->attachmentDisk();
        $uploaded = [];

        foreach ($validated['attachments'] as $file) {
            $path = $file->store("attachments/{$ledger->id}", $disk);

            $uploaded[] = $transaction->attachments()->create([
                'filename' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size' => $file->getSize(),
            ])->load('transaction');
        }

        return response()->json([
            'attachments' => $uploaded,
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, Ledger $ledger, Transaction $transaction, Attachment $attachment): StreamedResponse
    {
        $this->authorize('view', $ledger);

        $disk = Storage::disk($this->attachmentDisk());

        return $disk->response($attachment->path, $attachment->filename, [
            'Content-Type' => $attachment->mime_type,
        ]);
    }

    public function destroy(Request $request, Ledger $ledger, Transaction $transaction, Attachment $attachment): Response
    {
        $this->authorize('delete', $ledger);

        Storage::disk($this->attachmentDisk())->delete($attachment->path);
        $attachment->delete();

        return response()->noContent();
    }
}
