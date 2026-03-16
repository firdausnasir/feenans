<?php

namespace App\Http\Controllers\Ledger;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ParseImportRequest;
use App\Http\Requests\StoreImportRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\ImportMapping;
use App\Models\Ledger;
use App\Services\TransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
     * Mapping from detected bank headers to standard field names.
     *
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

    protected function ledgerDisk(): string
    {
        return (string) config('filesystems.ledger_disk', config('filesystems.default', 'local'));
    }

    public function create(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/import/index', [
            'ledger' => $ledger,
            'accounts' => $ledger->accounts()->visible()->orderBy('name')->get(['id', 'name']),
            'categories' => $ledger->categories()->with('children')->parents()->orderBy('position')->get(),
            'payees' => $ledger->payees()->orderBy('name')->get(['id', 'name']),
            'importHistory' => $ledger->importRecords()
                ->orderByDesc('imported_at')
                ->limit(10)
                ->get(),
        ]);
    }

    /**
     * Parse CSV and return headers + first 10 rows for column mapping.
     */
    public function parse(ParseImportRequest $request, Ledger $ledger): JsonResponse|RedirectResponse
    {
        $this->authorize('view', $ledger);

        $file = $request->file('file');

        if ($file === null || ! $file->isValid() || $file->getRealPath() === false || $file->getRealPath() === '') {
            return back()->withErrors(['file' => 'The uploaded file could not be read. Please upload it again.']);
        }

        $handle = @fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return back()->withErrors(['file' => 'The uploaded file could not be read. Please upload it again.']);
        }

        $allRows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $allRows[] = $row;
        }
        fclose($handle);

        $headers = array_shift($allRows) ?? [];
        $rows = array_slice($allRows, 0, 10);
        $total = count($allRows);

        // Store file in temp storage for later use
        $path = $file->store('imports/temp', $this->ledgerDisk());

        // Detect Malaysian bank format
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

        return response()->json($response);
    }

    /**
     * Import transactions from mapped CSV.
     */
    public function store(StoreImportRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('view', $ledger);

        $validated = $request->validated();

        $filePath = $validated['file_path'];

        if (! Storage::disk($this->ledgerDisk())->exists($filePath)) {
            return back()->withErrors(['file_path' => 'Import file not found. Please re-upload.']);
        }

        $disk = Storage::disk($this->ledgerDisk());
        $stream = $disk->readStream($filePath);

        if ($stream === null) {
            return back()->withErrors(['file_path' => 'Import file could not be read. Please re-upload.']);
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

            // Parse amount — remove currency symbols and commas
            $amount = (float) preg_replace('/[^0-9.\-]/', '', $amountStr);

            if ($amount == 0) {
                continue;
            }

            $description = isset($mapping['description']) && $mapping['description']
                ? ($rowAssoc[$mapping['description']] ?? null)
                : null;

            // Determine transaction type from sign or mapped column
            $type = TransactionType::Expense;
            if (isset($mapping['type']) && $mapping['type'] && isset($rowAssoc[$mapping['type']])) {
                $typeStr = strtolower(trim($rowAssoc[$mapping['type']]));
                if (str_contains($typeStr, 'income') || str_contains($typeStr, 'credit') || str_contains($typeStr, 'cr')) {
                    $type = TransactionType::Income;
                }
            } elseif ($amount > 0) {
                $type = TransactionType::Income;
            }

            // Ensure correct sign
            if ($type === TransactionType::Expense && $amount > 0) {
                $amount = -$amount;
            } elseif ($type === TransactionType::Income && $amount < 0) {
                $amount = abs($amount);
            }

            // Duplicate detection: same date + amount + description
            if ($skipDuplicates) {
                $exists = $ledger->transactions()
                    ->where('account_id', $account->id)
                    ->whereDate('transaction_date', $date)
                    ->where('amount', $amount)
                    ->when($description, fn ($q) => $q->where('description', $description))
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }
            }

            // Find category by name from CSV
            $category = null;
            if (isset($mapping['category']) && $mapping['category'] && isset($rowAssoc[$mapping['category']]) && trim($rowAssoc[$mapping['category']])) {
                $categoryName = trim($rowAssoc[$mapping['category']]);
                $category = $ledger->categories()
                    ->where('name', $categoryName)
                    ->first();
            }

            // Find or create payee
            $payee = null;
            if (isset($mapping['payee']) && $mapping['payee'] && isset($rowAssoc[$mapping['payee']]) && trim($rowAssoc[$mapping['payee']])) {
                $payeeName = trim($rowAssoc[$mapping['payee']]);
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

        // Record import history
        $ledger->importRecords()->create([
            'filename' => basename($filePath),
            'row_count' => $totalRows,
            'imported_count' => $imported,
            'skipped_count' => $skipped,
            'mapping_used' => $mapping,
            'imported_at' => CarbonImmutable::now(),
        ]);

        // Clean up temp file
        Storage::disk($this->ledgerDisk())->delete($filePath);

        return to_route('ledgers.transactions.index', $ledger)
            ->with('success', "Imported {$imported} transactions".($skipped > 0 ? ", skipped {$skipped} duplicates" : '').'.');
    }

    /**
     * Return saved import mappings for the ledger.
     */
    public function mappings(Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        $mappings = $ledger->importMappings()
            ->orderBy('name')
            ->get(['id', 'name', 'mapping']);

        return response()->json($mappings);
    }

    /**
     * Save a new import mapping configuration.
     */
    public function saveMapping(Request $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mapping' => ['required', 'array'],
        ]);

        $importMapping = $ledger->importMappings()->create([
            'name' => $validated['name'],
            'mapping' => $validated['mapping'],
        ]);

        return response()->json($importMapping, 201);
    }

    /**
     * Delete a saved import mapping.
     */
    public function destroyMapping(Ledger $ledger, ImportMapping $importMapping): JsonResponse
    {
        $this->authorize('view', $ledger);

        if ($importMapping->ledger_id !== $ledger->id) {
            abort(404);
        }

        $importMapping->delete();

        return response()->json(['message' => 'Mapping deleted.']);
    }

    /**
     * Detect bank format by checking header patterns.
     *
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

    /**
     * Try multiple date formats, returning Y-m-d string or null.
     */
    private function parseDate(string $value): ?string
    {
        $value = trim($value);

        $formats = [
            'm-d-y',   // 10-31-25
            'm/d/y',   // 10/31/25
            'm-d-Y',   // 10-31-2025
            'm/d/Y',   // 10/31/2025
            'd-m-Y',   // 31-10-2025
            'd/m/Y',   // 31/10/2025
            'd-m-y',   // 31-10-25
            'd/m/y',   // 31/10/25
            'Y-m-d',   // 2025-10-31
            'Y/m/d',   // 2025/10/31
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
                // Try next format
            }
        }

        // Fallback to Carbon's generic parser
        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (\Exception) {
            return null;
        }
    }
}
