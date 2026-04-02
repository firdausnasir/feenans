<?php

namespace App\Actions\Imports\UseCases;

use App\Data\Imports\Input\ParseImportData;
use App\Data\Imports\Output\PendingImportHandleData;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class CreatePendingImportHandleAction
{
    public function __invoke(ParseImportData $data): PendingImportHandleData
    {
        $file = $data->file;
        $disk = (string) config('filesystems.ledger_disk', config('filesystems.default', 'local'));
        $path = $file->store('imports/temp', $disk);

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file could not be read. Please upload it again.',
            ]);
        }

        return new PendingImportHandleData(
            disk: $disk,
            file_path: $path,
            pending_import_handle: Crypt::encryptString(json_encode([
                'ledger_id' => $data->ledger->id,
                'user_id' => $data->user->id,
                'disk' => $disk,
                'file_path' => $path,
            ], JSON_THROW_ON_ERROR)),
        );
    }
}
