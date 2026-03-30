<?php

namespace App\Http\Controllers\Ledger;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ParseImportRequest;
use App\Http\Requests\StoreImportMappingRequest;
use App\Http\Requests\StoreImportRequest;
use App\Models\Account;
use App\Models\ImportMapping;
use App\Models\Ledger;
use App\Services\TransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    /**
     * Malaysian bank header patterns for auto-detection.
     *
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

    public function create(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/import/index', [
            'parseResult' => $request->session()->get('importParseResult'),
            'accounts' => Inertia::defer(fn () => $ledger->accounts()->orderBy('position')->orderBy('name')->get()),
            'savedMappings' => Inertia::defer(fn () => $ledger->importMappings()->orderBy('name')->get(['id', 'name', 'mapping'])),
            'importHistory' => Inertia::defer(fn () => $ledger->importRecords()->orderByDesc('imported_at')->limit(50)->get()),
        ]);
    }

    protected function ledgerDisk(): string
    {
        return (string) config('filesystems.ledger_disk', config('filesystems.default', 'local'));
    }

    public function parse(ParseImportRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('view', $ledger);

        $file = $request->file('file');

        if ($file === null || ! $file->isValid() || $file->getRealPath() === false || $file->getRealPath() === '') {
            return to_route('ledgers.import.create', $ledger)
                ->withErrors(['file' => 'The uploaded file could not be read. Please upload it again.']);
        }

        $handle = @fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return to_route('ledgers.import.create', $ledger)
                ->withErrors(['file' => 'The uploaded file could not be read. Please upload it again.']);
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

        $request->session()->put($this->pendingImportFilePathSessionKey($ledger), $path);

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

        return to_route('ledgers.import.create', $ledger)->with('importParseResult', $response);
    }

    public function execute(StoreImportRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('view', $ledger);

        $validated = $request->validated();
        $filePath = (string) $request->session()->get($this->pendingImportFilePathSessionKey($ledger), '');

        if ($filePath === '' || ! Str::startsWith($filePath, 'imports/temp/') || ! Storage::disk($this->ledgerDisk())->exists($filePath)) {
            $request->session()->forget($this->pendingImportFilePathSessionKey($ledger));

            return to_route('ledgers.import.create', $ledger)
                ->withErrors(['file_path' => 'Import file not found. Please re-upload.']);
        }

        $disk = Storage::disk($this->ledgerDisk());
        $stream = $disk->readStream($filePath);

        if ($stream === false || $stream === null) {
            $request->session()->forget($this->pendingImportFilePathSessionKey($ledger));

            return to_route('ledgers.import.create', $ledger)
                ->withErrors(['file_path' => 'Import file could not be read. Please re-upload.']);
        }

        $headers = fgetcsv($stream) ?: [];
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

            try {
                $date = $this->parseDate($dateStr);
            } catch (\Exception) {
                continue;
            }

            if ($date === null) {
                continue;
            }

            $amount = (float) preg_replace('/[^0-9.\-]/', '', $amountStr);

            if ($amount == 0) {
                continue;
            }

            $description = isset($mapping['description']) && $mapping['description']
                ? ($rowAssoc[$mapping['description']] ?? null)
                : null;

            $type = TransactionType::Expense;
            if (isset($mapping['type']) && $mapping['type'] && isset($rowAssoc[$mapping['type']])) {
                $typeStr = strtolower(trim($rowAssoc[$mapping['type']]));
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
            if (isset($mapping['category']) && $mapping['category'] && isset($rowAssoc[$mapping['category']]) && trim($rowAssoc[$mapping['category']])) {
                $category = $ledger->categories()
                    ->where('name', trim($rowAssoc[$mapping['category']]))
                    ->first();
            }

            $payee = null;
            if (isset($mapping['payee']) && $mapping['payee'] && isset($rowAssoc[$mapping['payee']]) && trim($rowAssoc[$mapping['payee']])) {
                $payee = $ledger->payees()->firstOrCreate([
                    'name' => trim($rowAssoc[$mapping['payee']]),
                ]);
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

        Storage::disk($this->ledgerDisk())->delete($filePath);
        $request->session()->forget($this->pendingImportFilePathSessionKey($ledger));

        $message = "Imported {$imported} transactions";

        if ($skipped > 0) {
            $message .= ", skipped {$skipped} duplicates";
        }

        return to_route('ledgers.import.create', $ledger)->with('success', $message);
    }

    private function pendingImportFilePathSessionKey(Ledger $ledger): string
    {
        return "ledger-imports.{$ledger->id}.file_path";
    }

    public function storeMapping(StoreImportMappingRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('view', $ledger);

        $validated = $request->validated();

        $ledger->importMappings()->create([
            'name' => $validated['name'],
            'mapping' => $validated['mapping'],
        ]);

        return to_route('ledgers.import.create', $ledger)->with('success', 'Mapping saved.');
    }

    public function destroyMapping(Ledger $ledger, ImportMapping $importMapping): RedirectResponse
    {
        $this->authorize('view', $ledger);

        if ($importMapping->ledger_id !== $ledger->id) {
            abort(404);
        }

        $importMapping->delete();

        return to_route('ledgers.import.create', $ledger)->with('success', 'Mapping deleted.');
    }

    /**
     * @param  list<string>  $headers
     */
    private function detectBankFormat(array $headers): ?string
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
            }
        }

        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (\Exception) {
            return null;
        }
    }
}
