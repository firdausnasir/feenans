<?php

namespace App\Actions\Transactions\UseCases;

use App\Models\Attachment;
use App\Models\Ledger;
use App\Models\Transaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class StoreTransactionAttachmentsAction
{
    /**
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, Attachment>
     */
    public function __invoke(Ledger $ledger, Transaction $transaction, array $files): Collection
    {
        $disk = (string) config('app.attachment_disk', 'local');

        return collect($files)
            ->map(function (UploadedFile $file) use ($disk, $ledger, $transaction): Attachment {
                $path = $file->store("attachments/{$ledger->id}", $disk);

                /** @var Attachment $attachment */
                $attachment = $transaction->attachments()->create([
                    'filename' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                    'size' => $file->getSize(),
                ]);

                return $attachment->load('transaction');
            })
            ->values();
    }
}
