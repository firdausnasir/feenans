<?php

namespace App\Http\Controllers\Ledger;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ParseImportRequest;
use App\Http\Requests\StoreImportRequest;
use App\Models\Account;
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
            'accounts' => $ledger->accounts()->orderBy('name')->get(['id', 'name']),
            'categories' => $ledger->categories()->with('children')->parents()->orderBy('position')->get(),
            'payees' => $ledger->payees()->orderBy('name')->get(['id', 'name']),
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

        return response()->json([
            'headers' => $headers,
            'preview_rows' => $rows,
            'total_rows' => $total,
            'file_path' => $path,
        ]);
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

        $handle = fopen(Storage::disk($this->ledgerDisk())->path($filePath), 'r');
        $headers = fgetcsv($handle) ?: [];

        $mapping = $validated['mapping'];
        $account = Account::query()->findOrFail($validated['account_id']);
        $skipDuplicates = $validated['skip_duplicates'] ?? true;
        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
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
                $date = CarbonImmutable::parse($dateStr)->toDateString();
            } catch (\Exception) {
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

            // Find or create payee
            $payee = null;
            if (isset($mapping['payee']) && $mapping['payee'] && isset($rowAssoc[$mapping['payee']]) && trim($rowAssoc[$mapping['payee']])) {
                $payeeName = trim($rowAssoc[$mapping['payee']]);
                $payee = $ledger->payees()->firstOrCreate(['name' => $payeeName]);
            }

            $this->transactionService->store([
                'ledger' => $ledger,
                'account' => $account,
                'category' => null,
                'payee' => $payee,
                'transaction_type' => $type,
                'amount' => $amount,
                'description' => $description,
                'notes' => null,
                'transaction_date' => $date,
            ]);

            $imported++;
        }

        fclose($handle);

        // Clean up temp file
        Storage::disk($this->ledgerDisk())->delete($filePath);

        return to_route('ledgers.transactions.index', $ledger)
            ->with('success', "Imported {$imported} transactions".($skipped > 0 ? ", skipped {$skipped} duplicates" : '').'.');
    }
}
