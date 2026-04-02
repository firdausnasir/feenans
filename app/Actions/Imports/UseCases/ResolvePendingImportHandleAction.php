<?php

namespace App\Actions\Imports\UseCases;

use App\Data\Imports\Input\StoreImportData;
use App\Data\Imports\Output\PendingImportHandleData;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;

class ResolvePendingImportHandleAction
{
    public function __invoke(StoreImportData $data): PendingImportHandleData
    {
        try {
            $payload = json_decode(Crypt::decryptString((string) $data->pending_import_handle), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw ValidationException::withMessages([
                'pending_import_handle' => 'Import file not found. Please re-upload.',
            ]);
        }

        if (! is_array($payload)
            || ! is_string($payload['disk'] ?? null)
            || ! is_string($payload['file_path'] ?? null)
            || ! is_int($payload['ledger_id'] ?? null)
            || ! is_int($payload['user_id'] ?? null)
            || $payload['ledger_id'] !== $data->ledger->id
            || $payload['user_id'] !== $data->user->id
            || $payload['file_path'] === ''
            || ! Str::startsWith($payload['file_path'], 'imports/temp/')) {
            throw ValidationException::withMessages([
                'pending_import_handle' => 'Import file not found. Please re-upload.',
            ]);
        }

        return new PendingImportHandleData(
            disk: $payload['disk'],
            file_path: $payload['file_path'],
            pending_import_handle: $data->pending_import_handle,
        );
    }
}
