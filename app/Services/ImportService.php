<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\ImportMapping;
use App\Models\Ledger;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ImportService
{
    /**
     * @var array<string, list<string>>
     */
    private const array BANK_HEADER_PATTERNS = [
        'Maybank' => ['Transaction Date', 'Description', 'Debit', 'Credit'],
        'CIMB' => ['Date', 'Description', 'Amount(DR)', 'Amount(CR)'],
        'RHB' => ['Transaction Date', 'Transaction Description', 'Debit Amount', 'Credit Amount'],
        'Public Bank' => ['Date', 'Particulars', 'Withdrawal', 'Deposit'],
    ];

    /**
     * @var array<string, array<string, string>>
     */
    private const array BANK_MAPPINGS = [
        'Maybank' => [
            'date' => 'Transaction Date',
            'amount' => 'Debit',
            'description' => 'Description',
        ],
        'CIMB' => [
            'date' => 'Date',
            'amount' => 'Amount(DR)',
            'description' => 'Description',
        ],
        'RHB' => [
            'date' => 'Transaction Date',
            'amount' => 'Debit Amount',
            'description' => 'Transaction Description',
        ],
        'Public Bank' => [
            'date' => 'Date',
            'amount' => 'Withdrawal',
            'description' => 'Particulars',
        ],
    ];

    public function __construct(private readonly TransactionService $transactionService) {}

    public function ledgerDisk(): string
    {
        return (string) config('filesystems.ledger_disk', config('filesystems.default', 'local'));
    }

    /**
     * @return array{
     *     headers: array<int, string>,
     *     preview_rows: array<int, array<int, string|null>>,
     *     total_rows: int,
     *     file_path: string,
     *     detected_bank?: string,
     *     suggested_mapping?: array<string, string>
     * }
     */
    public function parseUploadedFile(UploadedFile $file): array
    {
        $realPath = $file->getRealPath();

        if (! $file->isValid() || $realPath === false || $realPath === '') {
            throw new \RuntimeException('The uploaded file could not be read. Please upload it again.');
        }

        $handle = @fopen($realPath, 'r');

        if ($handle === false) {
            throw new \RuntimeException('The uploaded file could not be read. Please upload it again.');
        }

        $allRows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $allRows[] = $row;
        }

        fclose($handle);

        $headers = array_shift($allRows) ?? [];
        $rows = array_slice($allRows, 0, 10);
        $total = count($allRows);
        $path = $file->store('imports/temp', $this->ledgerDisk());
        $detectedBank = $this->detectBankFormat($headers);
        $suggestedMapping = $detectedBank !== null
            ? (self::BANK_MAPPINGS[$detectedBank] ?? null)
            : null;

        $response = [
            'headers' => $headers,
            'preview_rows' => $rows,
            'total_rows' => $total,
            'file_path' => $path,
        ];

        if ($detectedBank !== null && $suggestedMapping !== null) {
            $response['detected_bank'] = $detectedBank;
            $response['suggested_mapping'] = $suggestedMapping;
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{imported: int, skipped: int}
     */
    public function executeImport(Ledger $ledger, array $validated): array
    {
        $filePath = (string) $validated['file_path'];
        $disk = Storage::disk($this->ledgerDisk());

        if (! $disk->exists($filePath)) {
            throw new \RuntimeException('Import file not found. Please re-upload.');
        }

        $stream = $disk->readStream($filePath);

        if ($stream === null) {
            throw new \RuntimeException('Import file could not be read. Please re-upload.');
        }

        $headers = fgetcsv($stream) ?: [];
        /** @var array<string, string|null> $mapping */
        $mapping = $validated['mapping'];
        $account = Account::query()->findOrFail($validated['account_id']);
        $skipDuplicates = $validated['skip_duplicates'] ?? true;
        $imported = 0;
        $skipped = 0;
        $totalRows = 0;

        while (($row = fgetcsv($stream)) !== false) {
            $totalRows++;
            $rowAssoc = array_combine($headers, $row);

            if (! $rowAssoc) {
                continue;
            }

            $dateStr = $rowAssoc[$mapping['date']] ?? null;
            $amountStr = $rowAssoc[$mapping['amount']] ?? null;

            if (! $dateStr || $amountStr === null) {
                continue;
            }

            $date = $this->parseDate($dateStr);

            if ($date === null) {
                continue;
            }

            $amount = (float) preg_replace('/[^0-9.\-]/', '', (string) $amountStr);

            if ($amount == 0.0) {
                continue;
            }

            $description = isset($mapping['description']) && $mapping['description']
                ? ($rowAssoc[$mapping['description']] ?? null)
                : null;

            $type = TransactionType::Expense;

            if (isset($mapping['type']) && $mapping['type'] && isset($rowAssoc[$mapping['type']])) {
                $typeStr = strtolower(trim((string) $rowAssoc[$mapping['type']]));

                if (str_contains($typeStr, 'income') || str_contains($typeStr, 'credit') || str_contains($typeStr, 'cr')) {
                    $type = TransactionType::Income;
                }
            } elseif ($amount > 0) {
                $type = TransactionType::Income;
            }

            if ($type === TransactionType::Expense && $amount > 0) {
                $amount = -$amount;
            } elseif ($type === TransactionType::Income && $amount < 0) {
                $amount = abs($amount);
            }

            if ($skipDuplicates) {
                $exists = $ledger->transactions()
                    ->where('account_id', $account->id)
                    ->whereDate('transaction_date', $date)
                    ->where('amount', $amount)
                    ->when($description, fn ($query) => $query->where('description', $description))
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }
            }

            $category = null;

            if (isset($mapping['category']) && $mapping['category'] && isset($rowAssoc[$mapping['category']]) && trim((string) $rowAssoc[$mapping['category']])) {
                $categoryName = trim((string) $rowAssoc[$mapping['category']]);
                $category = $ledger->categories()->where('name', $categoryName)->first();
            }

            $payee = null;

            if (isset($mapping['payee']) && $mapping['payee'] && isset($rowAssoc[$mapping['payee']]) && trim((string) $rowAssoc[$mapping['payee']])) {
                $payeeName = trim((string) $rowAssoc[$mapping['payee']]);
                $payee = $ledger->payees()->firstOrCreate(['name' => $payeeName]);
            }

            $this->transactionService->store([
                'ledger' => $ledger,
                'account' => $account,
                'category' => $category,
                'payee' => $payee,
                'transaction_type' => $type,
                'amount' => $amount,
                'description' => $description,
                'notes' => null,
                'transaction_date' => $date,
            ]);

            $imported++;
        }

        fclose($stream);

        $ledger->importRecords()->create([
            'filename' => basename($filePath),
            'row_count' => $totalRows,
            'imported_count' => $imported,
            'skipped_count' => $skipped,
            'mapping_used' => $mapping,
            'imported_at' => CarbonImmutable::now(),
        ]);

        $disk->delete($filePath);

        return [
            'imported' => $imported,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, mapping: array<string, mixed>}>
     */
    public function mappingsForLedger(Ledger $ledger): array
    {
        return $ledger->importMappings()
            ->orderBy('name')
            ->get(['id', 'name', 'mapping'])
            ->map(fn (ImportMapping $mapping): array => [
                'id' => $mapping->id,
                'name' => $mapping->name,
                'mapping' => $mapping->mapping,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function historyForLedger(Ledger $ledger): array
    {
        return $ledger->importRecords()
            ->orderByDesc('imported_at')
            ->limit(50)
            ->get()
            ->map(fn ($record): array => [
                'id' => $record->id,
                'filename' => $record->filename,
                'row_count' => $record->row_count,
                'imported_count' => $record->imported_count,
                'skipped_count' => $record->skipped_count,
                'mapping_used' => $record->mapping_used,
                'imported_at' => $record->imported_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $headers
     */
    public function detectBankFormat(array $headers): ?string
    {
        $normalizedHeaders = array_map(
            fn (string $header): string => strtolower(trim($header)),
            $headers,
        );

        foreach (self::BANK_HEADER_PATTERNS as $bank => $requiredHeaders) {
            $allFound = true;

            foreach ($requiredHeaders as $requiredHeader) {
                if (! in_array(strtolower($requiredHeader), $normalizedHeaders, true)) {
                    $allFound = false;
                    break;
                }
            }

            if ($allFound) {
                return $bank;
            }
        }

        return null;
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);

        $formats = [
            'm-d-y',
            'm/d/y',
            'm-d-Y',
            'm/d/Y',
            'd-m-Y',
            'd/m/Y',
            'd-m-y',
            'd/m/y',
            'Y-m-d',
            'Y/m/d',
        ];

        foreach ($formats as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat("!{$format}", $value);

                if ($parsed !== false) {
                    $warnings = CarbonImmutable::getLastErrors();

                    if (($warnings['warning_count'] ?? 0) === 0 && ($warnings['error_count'] ?? 0) === 0) {
                        return $parsed->toDateString();
                    }
                }
            } catch (\Exception) {
                continue;
            }
        }

        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (\Exception) {
            return null;
        }
    }
}
