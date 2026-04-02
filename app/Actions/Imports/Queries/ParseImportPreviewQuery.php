<?php

namespace App\Actions\Imports\Queries;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ParseImportPreviewQuery
{
    /**
     * @return array{headers: list<string>, preview_rows: list<list<string>>, total_rows: int}
     */
    public function __invoke(UploadedFile $file): array
    {
        $realPath = $file->getRealPath();

        if (! $file->isValid() || $realPath === false || $realPath === '' || ! is_readable($realPath)) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file could not be read. Please upload it again.',
            ]);
        }

        $handle = fopen($realPath, 'r');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file could not be read. Please upload it again.',
            ]);
        }

        try {
            $allRows = [];

            while (($row = fgetcsv($handle)) !== false) {
                $allRows[] = array_map(
                    static fn (mixed $value): string => (string) ($value ?? ''),
                    $row,
                );
            }
        } finally {
            fclose($handle);
        }

        $headers = array_shift($allRows) ?? [];

        return [
            'headers' => $headers,
            'preview_rows' => array_slice($allRows, 0, 10),
            'total_rows' => count($allRows),
        ];
    }
}
