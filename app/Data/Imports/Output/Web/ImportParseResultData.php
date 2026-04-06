<?php

namespace App\Data\Imports\Output\Web;

use App\Data\Shared\Output\BaseOutputData;

class ImportParseResultData extends BaseOutputData
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $preview_rows
     * @param  array<string, string>|null  $suggested_mapping
     */
    public function __construct(
        public array $headers,
        public array $preview_rows,
        public int $total_rows,
        public string $file_path,
        public ?string $detected_bank = null,
        public ?array $suggested_mapping = null,
        public ?string $pending_import_handle = null,
    ) {}

    /**
     * @param  array<string, mixed>  $result
     */
    public static function fromSession(array $result): self
    {
        $headers = array_map(
            static fn (mixed $header): string => (string) $header,
            array_values($result['headers'] ?? []),
        );
        $previewRows = array_map(
            static fn (mixed $row): array => array_map(
                static fn (mixed $value): string => (string) $value,
                is_array($row) ? array_values($row) : [],
            ),
            array_values($result['preview_rows'] ?? []),
        );
        $suggestedMapping = isset($result['suggested_mapping']) && is_array($result['suggested_mapping'])
            ? array_map(static fn (mixed $value): string => (string) $value, $result['suggested_mapping'])
            : null;

        return new self(
            headers: $headers,
            preview_rows: $previewRows,
            total_rows: (int) ($result['total_rows'] ?? 0),
            file_path: (string) ($result['file_path'] ?? ''),
            detected_bank: isset($result['detected_bank']) ? (string) $result['detected_bank'] : null,
            suggested_mapping: $suggestedMapping,
            pending_import_handle: null,
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
            'file_path' => $this->file_path,
        ];

        if ($this->detected_bank !== null && $this->suggested_mapping !== null) {
            $payload['detected_bank'] = $this->detected_bank;
            $payload['suggested_mapping'] = $this->suggested_mapping;
        }

        return $payload;
    }
}
