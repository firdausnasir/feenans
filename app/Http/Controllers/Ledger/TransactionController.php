<?php

namespace App\Http\Controllers\Ledger;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\TransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactionService,
        private readonly BudgetService $budgetService,
    ) {}

    public function index(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        $filters = $this->resolveFilters($request);

        $page = $request->integer('page', 1);

        return Inertia::render('ledgers/transactions/index', [
            'filters' => $filters,
            'accounts' => fn () => $ledger->accounts()->visible()->orderBy('position')->orderBy('name')->get(['id', 'ledger_id', 'name', 'current_balance', 'color']),
            'categories' => fn () => $ledger->categories()->orderBy('position')->get(),
            'payees' => fn () => $ledger->payees()->orderBy('name')->get(),
            'tags' => fn () => $ledger->tags()->orderBy('name')->get(),
            'transactions' => Inertia::defer(function () use ($ledger, $request, $filters, $page) {
                $query = $ledger->transactions()
                    ->with(['account', 'category', 'payee', 'tags', 'transferPair'])
                    ->withCount('splits')
                    ->orderByDesc('transaction_date')
                    ->orderByDesc('id');

                $this->applyTransactionFilters($query, $filters);

                return $query->paginate(
                    $request->integer('per_page', 25),
                    ['*'],
                    'page',
                    $page,
                );
            }),
        ]);
    }

    public function store(StoreTransactionRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('view', $ledger);

        $this->resolveInlinePayee($request, $ledger);

        if ($request->string('transaction_type')->value() === TransactionType::Transfer->value) {
            [$outgoing, $incoming] = $this->transactionService->storeTransfer($ledger, [
                'from_account' => Account::query()->findOrFail($request->integer('account_id')),
                'to_account' => Account::query()->findOrFail($request->integer('to_account_id')),
                'amount' => $request->input('amount'),
                'description' => $request->input('description'),
                'notes' => $request->input('notes'),
                'transaction_date' => $request->date('transaction_date')?->toDateString() ?? now()->toDateString(),
            ]);

            if ($request->filled('tag_ids')) {
                $tagIds = $request->input('tag_ids', []);
                $outgoing->tags()->sync($tagIds);
                $incoming->tags()->sync($tagIds);
            }

            if ($request->hasFile('attachments')) {
                $files = $request->file('attachments');
                $this->storeAttachments($ledger, $outgoing, $files);
                $this->storeAttachments($ledger, $incoming, $files);
            }

            $this->celebrateFirstTransaction();

            return back();
        }

        $transaction = $this->transactionService->store([
            'ledger' => $ledger,
            'account' => Account::query()->findOrFail($request->integer('account_id')),
            'category' => $request->filled('category_id') ? Category::query()->findOrFail($request->integer('category_id')) : null,
            'payee' => $request->filled('payee_id') ? Payee::query()->findOrFail($request->integer('payee_id')) : null,
            'transaction_type' => TransactionType::from($request->string('transaction_type')->value()),
            'amount' => $request->input('amount'),
            'description' => $request->input('description'),
            'notes' => $request->input('notes'),
            'transaction_date' => $request->date('transaction_date')?->toDateString() ?? now()->toDateString(),
            'splits' => $request->validated('splits'),
        ]);

        if ($request->filled('tag_ids')) {
            $transaction->tags()->sync($request->input('tag_ids', []));
        }

        if ($request->hasFile('attachments')) {
            $this->storeAttachments($ledger, $transaction, $request->file('attachments'));
        }

        $this->budgetService->checkThresholds(
            $ledger,
            $request->filled('category_id') ? $request->integer('category_id') : null,
        );

        $this->celebrateFirstTransaction();

        return back();
    }

    public function edit(Request $request, Ledger $ledger, Transaction $transaction): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/transactions/edit', [
            'ledger' => $ledger,
            'transaction_id' => $transaction->id,
        ]);
    }

    public function export(Request $request, Ledger $ledger): StreamedResponse
    {
        $this->authorize('view', $ledger);

        ['start' => $start, 'end' => $end] = $ledger->cycleBounds(CarbonImmutable::now());

        $dateFrom = $request->get('date_from', $start->toDateString());
        $dateTo = $request->get('date_to', $end->toDateString());

        $query = $ledger->transactions()
            ->with(['account', 'category', 'payee'])
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        $accountIds = $this->resolveArrayFilter($request, 'account_ids');
        if ($accountIds !== null) {
            $query->whereIn('account_id', $accountIds);
        }

        $categoryIds = $this->resolveArrayFilter($request, 'category_ids');
        if ($categoryIds !== null) {
            $query->whereIn('category_id', $categoryIds);
        }

        $transactionTypes = $this->resolveArrayFilter($request, 'transaction_types');
        if ($transactionTypes !== null) {
            $query->whereIn('transaction_type', $transactionTypes);
        }

        $payeeIds = $this->resolveArrayFilter($request, 'payee_ids');
        if ($payeeIds !== null) {
            $query->whereIn('payee_id', $payeeIds);
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $filename = 'transactions-'.$dateFrom.'-'.$dateTo.'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Date', 'Description', 'Type', 'Account', 'Category', 'Payee', 'Amount', 'Notes']);

            $query->chunk(500, function ($transactions) use ($handle) {
                foreach ($transactions as $t) {
                    fputcsv($handle, [
                        $t->transaction_date->toDateString(),
                        $t->description ?? '',
                        $t->transaction_type instanceof TransactionType ? $t->transaction_type->value : $t->transaction_type,
                        $t->account?->name ?? '',
                        $t->category?->name ?? '',
                        $t->payee?->name ?? '',
                        number_format((float) $t->amount, 2, '.', ''),
                        $t->notes ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function update(Request $request, Ledger $ledger, Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $validated = $request->validate([
            'transaction_type' => ['required', 'in:expense,income,transfer'],
            'transaction_date' => ['required', 'date'],
            'account_id' => ['required', 'exists:accounts,id'],
            'to_account_id' => ['nullable', 'exists:accounts,id', 'different:account_id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'payee_id' => ['nullable', 'exists:payees,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'splits' => ['nullable', 'array', 'min:2'],
            'splits.*.amount' => ['nullable', 'numeric'],
            'splits.*.category_id' => ['nullable', 'exists:categories,id'],
            'splits.*.description' => ['nullable', 'string', 'max:255'],
        ]);

        $type = TransactionType::from($validated['transaction_type']);
        $wasTransfer = $transaction->transfer_pair_id !== null;
        $isTransfer = $type === TransactionType::Transfer;

        if ($wasTransfer && ! $isTransfer) {
            // Converting from transfer to single transaction
            $this->transactionService->convertTransferToSingle($transaction, [
                'transaction_type' => $type,
                'account' => Account::findOrFail($validated['account_id']),
                'category' => isset($validated['category_id']) ? Category::find($validated['category_id']) : null,
                'payee' => isset($validated['payee_id']) ? Payee::find($validated['payee_id']) : null,
                'amount' => $validated['amount'],
                'description' => $validated['description'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'transaction_date' => $validated['transaction_date'],
            ]);
        } elseif (! $wasTransfer && $isTransfer) {
            // Converting from single to transfer
            $this->transactionService->convertSingleToTransfer($transaction, $ledger, [
                'from_account' => Account::findOrFail($validated['account_id']),
                'to_account' => Account::findOrFail($validated['to_account_id']),
                'amount' => $validated['amount'],
                'description' => $validated['description'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'transaction_date' => $validated['transaction_date'],
            ]);
        } elseif ($isTransfer) {
            // Transfer to transfer update
            $this->transactionService->update($transaction, [
                'account' => Account::findOrFail($validated['account_id']),
                'to_account' => Account::findOrFail($validated['to_account_id']),
                'amount' => $validated['amount'],
                'description' => $validated['description'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'transaction_date' => $validated['transaction_date'],
            ]);
        } else {
            // Regular transaction update
            $this->transactionService->update($transaction, [
                'transaction_type' => $type,
                'account' => Account::findOrFail($validated['account_id']),
                'category' => isset($validated['category_id']) ? Category::find($validated['category_id']) : null,
                'payee' => isset($validated['payee_id']) ? Payee::find($validated['payee_id']) : null,
                'amount' => $validated['amount'],
                'description' => $validated['description'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'transaction_date' => $validated['transaction_date'],
                'splits' => $validated['splits'] ?? null,
            ]);
        }

        // Sync tags
        $transaction->tags()->sync($validated['tag_ids'] ?? []);

        $this->budgetService->checkThresholds($ledger, $validated['category_id'] ?? null);

        return back()->with('success', 'Transaction updated.');
    }

    public function destroy(Ledger $ledger, Transaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $this->transactionService->delete($transaction);

        return back()->with('success', 'Transaction deleted.');
    }

    public function bulkUpdate(Request $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'action' => ['required', 'in:change_category,change_account,change_payee'],
            'value' => ['required', 'integer'],
        ]);

        $query = $ledger->transactions()->whereIn('id', $validated['ids'])
            ->where('transaction_type', '!=', TransactionType::Transfer);

        $field = match ($validated['action']) {
            'change_category' => 'category_id',
            'change_account' => 'account_id',
            'change_payee' => 'payee_id',
        };

        $query->update([$field => $validated['value']]);

        return back()->with('success', 'Transactions updated.');
    }

    public function bulkDestroy(Request $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        // Get transfer pair IDs before deleting
        $transferPairIds = $ledger->transactions()
            ->whereIn('id', $validated['ids'])
            ->whereNotNull('transfer_pair_id')
            ->pluck('transfer_pair_id')
            ->all();

        // Find all transaction IDs that share those pair IDs
        $pairedTransactionIds = $transferPairIds !== []
            ? $ledger->transactions()
                ->whereIn('transfer_pair_id', $transferPairIds)
                ->pluck('id')
                ->all()
            : [];

        $allIds = array_unique(array_merge($validated['ids'], $pairedTransactionIds));

        $ledger->transactions()->whereIn('id', $allIds)->delete();

        return back()->with('success', 'Transactions deleted.');
    }

    public function selectAll(Request $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        $filters = $this->resolveFilters($request);

        $query = $ledger->transactions();

        $this->applyTransactionFilters($query, $filters);

        return response()->json(['ids' => $query->pluck('id')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFilters(Request $request): array
    {
        return [
            'search' => $request->input('search'),
            'date_from' => $request->input('date_from', ''),
            'date_to' => $request->input('date_to', ''),
            'account_ids' => $this->resolveArrayFilter($request, 'account_ids') ?? [],
            'category_ids' => $this->resolveArrayFilter($request, 'category_ids') ?? [],
            'transaction_types' => $this->resolveArrayFilter($request, 'transaction_types') ?? [],
            'payee_ids' => $this->resolveArrayFilter($request, 'payee_ids') ?? [],
            'tag_ids' => $this->resolveArrayFilter($request, 'tag_ids') ?? [],
            'bill_id' => $request->input('bill_id'),
            'uncategorized' => $request->input('uncategorized'),
        ];
    }

    private function applyTransactionFilters($query, array $filters): void
    {
        if (! empty($filters['date_from'])) {
            $query->where('transaction_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('transaction_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['account_ids'])) {
            $query->whereIn('account_id', $filters['account_ids']);
        }

        if (! empty($filters['category_ids'])) {
            $query->whereIn('category_id', $filters['category_ids']);
        }

        if (! empty($filters['transaction_types'])) {
            $query->whereIn('transaction_type', $filters['transaction_types']);
        }

        if (! empty($filters['payee_ids'])) {
            $query->whereIn('payee_id', $filters['payee_ids']);
        }

        if (! empty($filters['tag_ids'])) {
            $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $filters['tag_ids']));
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q
                ->where('description', 'like', "%{$search}%")
                ->orWhere('notes', 'like', "%{$search}%"));
        }

        if (! empty($filters['bill_id'])) {
            $query->where('bill_id', $filters['bill_id']);
        }

        if ($filters['uncategorized'] === '1') {
            $query->whereNull('category_id')
                ->where('transaction_type', '!=', TransactionType::Transfer);
        }
    }

    /**
     * Resolve an array filter from the request.
     * Supports both `key[]` (standard array) and `key` (comma-separated or single value) formats.
     *
     * @return string[]|null
     */
    private function resolveArrayFilter(Request $request, string $key): ?array
    {
        $value = $request->input($key);

        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if (is_array($value)) {
            $filtered = array_filter($value, fn ($v) => $v !== null && $v !== '');

            return $filtered !== [] ? array_values($filtered) : null;
        }

        return [(string) $value];
    }

    /**
     * If the request carries a new_payee_name (and no payee_id), create the
     * payee on the fly and merge its id back into the request so downstream
     * code can treat it as a regular payee_id.
     */
    private function resolveInlinePayee(Request $request, Ledger $ledger): void
    {
        $newPayeeName = trim((string) $request->input('new_payee_name'));

        if ($newPayeeName === '' || $request->filled('payee_id')) {
            return;
        }

        $payee = $ledger->payees()->create(['name' => $newPayeeName]);
        $request->merge(['payee_id' => $payee->id]);
    }

    private function celebrateFirstTransaction(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $onboardingData = $user->onboarding_data ?? [];

        if (empty($onboardingData['first_transaction_celebrated'])) {
            $onboardingData['first_transaction_celebrated'] = true;
            $user->update(['onboarding_data' => $onboardingData]);
            session()->flash('first_transaction', true);
        }
    }

    /**
     * Store attachment files for a given transaction.
     *
     * @param  array<int, UploadedFile>  $files
     */
    private function storeAttachments(Ledger $ledger, Transaction $transaction, array $files): void
    {
        $disk = (string) config('app.attachment_disk', 'local');

        foreach ($files as $file) {
            $path = $file->store("attachments/{$ledger->id}", $disk);

            $transaction->attachments()->create([
                'filename' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size' => $file->getSize(),
            ]);
        }
    }
}
