<?php

namespace App\Data\Imports\Output\Api;

use App\Data\Imports\Output\Web\ImportParseResultData as WebImportParseResultData;

class ImportParseResultData extends WebImportParseResultData
{
    public static function fromWebResult(WebImportParseResultData $result): self
    {
        return new self(
            headers: $result->headers,
            preview_rows: $result->preview_rows,
            total_rows: $result->total_rows,
            file_path: $result->file_path,
            detected_bank: $result->detected_bank,
            suggested_mapping: $result->suggested_mapping,
            pending_import_handle: $result->pending_import_handle,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'headers' => $this->headers,
            'preview_rows' => $this->preview_rows,
            'total_rows' => $this->total_rows,
            'pending_import_handle' => $this->pending_import_handle,
        ];

        if ($this->detected_bank !== null && $this->suggested_mapping !== null) {
            $payload['detected_bank'] = $this->detected_bank;
            $payload['suggested_mapping'] = $this->suggested_mapping;
        }

        return $payload;
    }
}
