<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\Ledger;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

    public function store(StoreAttachmentRequest $request, Ledger $ledger, Transaction $transaction): RedirectResponse
    {
        $this->authorize('view', $ledger);

        $validated = $request->validated();

        $uploaded = [];
        $disk = (string) config('app.attachment_disk', 'local');

        foreach ($validated['attachments'] as $file) {
            $path = $file->store("attachments/{$ledger->id}", $disk);

            $uploaded[] = $transaction->attachments()->create([
                'filename' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size' => $file->getSize(),
            ])->load('transaction');
        }

        return redirect()
            ->to($this->redirectUrl($request, $ledger, $transaction))
            ->with('success', count($uploaded) === 1 ? 'Attachment uploaded.' : 'Attachments uploaded.')
            ->with('attachment_uploads', AttachmentResource::collection(collect($uploaded))->resolve());
    }

    public function show(Request $request, Ledger $ledger, Transaction $transaction, Attachment $attachment): StreamedResponse
    {
        $this->authorize('view', $ledger);

        return Storage::response($attachment->path, $attachment->filename, [
            'Content-Type' => $attachment->mime_type,
        ]);
    }

    public function destroy(Request $request, Ledger $ledger, Transaction $transaction, Attachment $attachment): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $disk = (string) config('app.attachment_disk', 'local');

        try {
            Storage::disk($disk)->delete($attachment->path);
        } catch (\Exception $e) {
            // Ignore file not found errors - file may already be deleted
        }
        $attachment->delete();

        return redirect()
            ->to($this->redirectUrl($request, $ledger, $transaction))
            ->with('success', 'Attachment deleted.')
            ->with('deleted_attachment_id', $attachment->id);
    }

    private function redirectUrl(Request $request, Ledger $ledger, Transaction $transaction): string
    {
        return $request->headers->get('referer') ?: route('ledgers.transactions.edit', [$ledger, $transaction]);
    }
}
