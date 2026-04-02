<?php

namespace App\Actions\Imports\UseCases;

use App\Actions\Transactions\UseCases\StoreTransactionAction;
use App\Data\Imports\Input\StoreImportData;
use App\Data\Imports\Output\Api\ImportExecutionData;
use App\Data\Imports\Output\PendingImportHandleData;
use App\Data\Transactions\Input\StoreTransactionData;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExecuteImportAction
{
    public function __construct(
        private readonly ResolvePendingImportHandleAction $resolvePendingImportHandle,
        private readonly StoreTransactionAction $storeTransaction,
    ) {}

    public function __invoke(StoreImportData $data): ImportExecutionData
    {
        $pendingHandle = $data->pending_import_handle !== null
            ? ($this->resolvePendingImportHandle)($data)
            : new PendingImportHandleData(
                disk: (string) config('filesystems.ledger_disk', config('filesystems.default', 'local')),
                file_path: (string) $data->file_path,
            );

        $disk = Storage::disk($pendingHandle->disk);

        if (! Str::startsWith($pendingHandle->file_path, 'imports/temp/') || ! $disk->exists($pendingHandle->file_path)) {
            throw ValidationException::withMessages([
                $this->pendingHandleAttribute($data) => 'Import file not found. Please re-upload.',
            ]);
        }

        $stream = $disk->readStream($pendingHandle->file_path);

        if ($stream === false || $stream === null) {
            throw ValidationException::withMessages([
                $this->pendingHandleAttribute($data) => 'Import file could not be read. Please re-upload.',
            ]);
        }

        $headers = fgetcsv($stream) ?: [];
        $mapping = $data->mapping;
        $account = $data->ledger->accounts()->findOrFail($data->account_id);
        $skipDuplicates = $data->skip_duplicates ?? true;
        $imported = 0;
        $skipped = 0;
        $totalRows = 0;

        try {
            while (($row = fgetcsv($stream)) !== false) {
                $totalRows++;
                $rowAssoc = array_combine($headers, $row);

                if (! is_array($rowAssoc)) {
                    continue;
                }

                $dateStr = $this->mappedValue($rowAssoc, $mapping['date'] ?? null);
                $amountStr = $this->mappedValue($rowAssoc, $mapping['amount'] ?? null);

                if ($dateStr === null || $amountStr === null) {
                    continue;
                }

                try {
                    $date = $this->parseDate($dateStr);
                } catch (\Exception) {
                    continue;
                }

                if ($date === null) {
                    continue;
                }

                $amount = (float) preg_replace('/[^0-9.\-]/', '', $amountStr);

                if ($amount == 0.0) {
                    continue;
                }

                $description = $this->mappedValue($rowAssoc, $mapping['description'] ?? null);
                $type = $this->resolveTransactionType($mapping, $rowAssoc, $amount);
                $amount = $this->normalizeImportedAmount($type, $amount);

                if ($skipDuplicates && $this->duplicateExists($data->ledger, $account, $date, $amount, $description)) {
                    $skipped++;

                    continue;
                }

                $category = $this->resolveCategory($data->ledger, $mapping, $rowAssoc);
                $payee = $this->resolvePayee($data->ledger, $mapping, $rowAssoc);

                ($this->storeTransaction)(new StoreTransactionData(
                    account_id: $account->id,
                    transaction_type: $type->value,
                    amount: abs($amount),
                    transaction_date: $date,
                    category_id: $category?->id,
                    payee_id: $payee?->id,
                    description: $description,
                    notes: null,
                    ledger: $data->ledger,
                    user: $data->user,
                ));

                $imported++;
            }
        } finally {
            fclose($stream);
        }

        $data->ledger->importRecords()->create([
            'filename' => basename($pendingHandle->file_path),
            'row_count' => $totalRows,
            'imported_count' => $imported,
            'skipped_count' => $skipped,
            'mapping_used' => $mapping,
            'imported_at' => CarbonImmutable::now(),
        ]);

        $disk->delete($pendingHandle->file_path);

        $message = "Imported {$imported} transactions";

        if ($skipped > 0) {
            $message .= ", skipped {$skipped} duplicates";
        }

        return new ImportExecutionData(
            row_count: $totalRows,
            imported_count: $imported,
            skipped_count: $skipped,
            message: $message,
        );
    }

    /**
     * @param  array<string, string>  $mapping
     * @param  array<string, string>  $rowAssoc
     */
    private function resolveTransactionType(array $mapping, array $rowAssoc, float $amount): TransactionType
    {
        $typeColumn = $mapping['type'] ?? null;

        if (is_string($typeColumn) && $typeColumn !== '' && isset($rowAssoc[$typeColumn])) {
            $typeStr = strtolower(trim($rowAssoc[$typeColumn]));

            if (str_contains($typeStr, 'income') || str_contains($typeStr, 'credit') || str_contains($typeStr, 'cr')) {
                return TransactionType::Income;
            }
        } elseif ($amount > 0) {
            return TransactionType::Income;
        }

        return TransactionType::Expense;
    }

    private function normalizeImportedAmount(TransactionType $type, float $amount): float
    {
        if ($type === TransactionType::Expense && $amount > 0) {
            return -$amount;
        }

        if ($type === TransactionType::Income && $amount < 0) {
            return abs($amount);
        }

        return $amount;
    }

    private function duplicateExists(Ledger $ledger, Account $account, string $date, float $amount, ?string $description): bool
    {
        return $ledger->transactions()
            ->where('account_id', $account->id)
            ->whereDate('transaction_date', $date)
            ->where('amount', $amount)
            ->when($description, fn ($query) => $query->where('description', $description))
            ->exists();
    }

    /**
     * @param  array<string, string>  $mapping
     * @param  array<string, string>  $rowAssoc
     */
    private function resolveCategory(Ledger $ledger, array $mapping, array $rowAssoc): ?Category
    {
        $categoryColumn = $mapping['category'] ?? null;
        $name = $this->mappedValue($rowAssoc, $categoryColumn);

        if ($name === null) {
            return null;
        }

        return $ledger->categories()->where('name', $name)->first();
    }

    /**
     * @param  array<string, string>  $mapping
     * @param  array<string, string>  $rowAssoc
     */
    private function resolvePayee(Ledger $ledger, array $mapping, array $rowAssoc): ?Payee
    {
        $payeeColumn = $mapping['payee'] ?? null;
        $name = $this->mappedValue($rowAssoc, $payeeColumn);

        if ($name === null) {
            return null;
        }

        return $ledger->payees()->firstOrCreate([
            'name' => $name,
        ]);
    }

    /**
     * @param  array<string, string>  $rowAssoc
     */
    private function mappedValue(array $rowAssoc, ?string $column): ?string
    {
        if (! is_string($column) || $column === '' || ! isset($rowAssoc[$column])) {
            return null;
        }

        $value = trim($rowAssoc[$column]);

        return $value === '' ? null : $value;
    }

    private function pendingHandleAttribute(StoreImportData $data): string
    {
        return $data->pending_import_handle !== null ? 'pending_import_handle' : 'file_path';
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
            }
        }

        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (\Exception) {
            return null;
        }
    }
}
