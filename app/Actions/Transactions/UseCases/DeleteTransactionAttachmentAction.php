<?php

namespace App\Actions\Transactions\UseCases;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;

class DeleteTransactionAttachmentAction
{
    public function __invoke(Attachment $attachment): Attachment
    {
        $attachment->loadMissing('transaction');
        $deletedAttachment = clone $attachment;
        $disk = (string) config('app.attachment_disk', 'local');

        Storage::disk($disk)->delete($attachment->path);

        $attachment->delete();

        return $deletedAttachment;
    }
}
