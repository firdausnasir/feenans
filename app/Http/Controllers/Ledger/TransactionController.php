<?php

namespace App\Http\Controllers\Ledger;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkDestroyTransactionsRequest;
use App\Http\Requests\BulkUpdateTransactionsRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
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
                    ->with([
                        'account',
                        'category',
                        'payee',
                        'tags',
                        'transferPair.account',
                        'splits.category',
                        'splits.payee',
                    ])
                    ->withCount('splits')
                    ->withCount('attachments')
                    ->orderByDesc('transaction_date')
                    ->orderByDesc('id');

                $this->applyTransactionFilters($query, $filters);

                $transactions = $query->paginate(
                    $request->integer('per_page', 25),
                    ['*'],
                    'page',
                    $page,
                );

                $this->normalizeTransferPairRelations($ledger, $transactions);

                return $transactions;
            })->deepMerge(),
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

        $categories = $ledger->categories()->orderBy('position')->get();

        $transaction->load([
            'account',
            'category',
            'payee',
            'tags',
            'splits.category',
            'splits.payee',
            'attachments.transaction',
            'transferPair.account',
            'transferPair.category',
            'transferPair.payee',
        ])->loadCount('splits');

        return Inertia::render('ledgers/transactions/edit', [
            'ledger' => $ledger,
            'transaction' => fn () => TransactionResource::make($transaction)->resolve(),
            'accounts' => fn () => $ledger->accounts()->visible()->orderBy('position')->orderBy('name')->get(['id', 'ledger_id', 'name', 'current_balance', 'color']),
            'categories' => fn () => $this->flattenCategories($categories),
            'payees' => fn () => $ledger->payees()->orderBy('name')->get(),
            'tags' => fn () => $ledger->tags()->orderBy('name')->get(),
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

    public function update(UpdateTransactionRequest $request, Ledger $ledger, Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $validated = $this->resolveValidatedInlinePayee($request->validated(), $ledger);

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

        return redirect()
            ->route('ledgers.transactions.index', $ledger)
            ->with('success', 'Transaction updated.');
    }

    public function destroy(Ledger $ledger, Transaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $this->transactionService->delete($transaction);

        return redirect()
            ->route('ledgers.transactions.index', $ledger)
            ->with('success', 'Transaction deleted.');
    }

    public function bulkUpdate(BulkUpdateTransactionsRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $validated = $request->validated();

        $ids = $this->resolveBulkTransactionIds($request, $ledger, $validated);

        if ($ids === []) {
            return back()->with('success', 'Transactions updated.');
        }

        $query = $ledger->transactions()->whereIn('id', $ids)
            ->where('transaction_type', '!=', TransactionType::Transfer);

        $field = match ($validated['action']) {
            'change_category' => 'category_id',
            'change_account' => 'account_id',
            'change_payee' => 'payee_id',
        };

        $query->update([$field => $validated['value']]);

        return back()->with('success', 'Transactions updated.');
    }

    public function bulkDestroy(BulkDestroyTransactionsRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $validated = $request->validated();

        $ids = $this->resolveBulkTransactionIds($request, $ledger, $validated);

        if ($ids === []) {
            return back()->with('success', 'Transactions deleted.');
        }

        // Get transfer pair IDs before deleting
        $transferPairIds = $ledger->transactions()
            ->whereIn('id', $ids)
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

        $allIds = array_unique(array_merge($ids, $pairedTransactionIds));

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
        return $this->normalizeFilters($request->all());
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $input): array
    {
        return [
            'search' => $input['search'] ?? null,
            'date_from' => (string) ($input['date_from'] ?? ''),
            'date_to' => (string) ($input['date_to'] ?? ''),
            'account_ids' => $this->resolveArrayFilterFromValue($input['account_ids'] ?? null) ?? [],
            'category_ids' => $this->resolveArrayFilterFromValue($input['category_ids'] ?? null) ?? [],
            'transaction_types' => $this->resolveArrayFilterFromValue($input['transaction_types'] ?? null) ?? [],
            'payee_ids' => $this->resolveArrayFilterFromValue($input['payee_ids'] ?? null) ?? [],
            'tag_ids' => $this->resolveArrayFilterFromValue($input['tag_ids'] ?? null) ?? [],
            'bill_id' => $input['bill_id'] ?? null,
            'uncategorized' => $input['uncategorized'] ?? null,
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
        return $this->resolveArrayFilterFromValue($request->input($key));
    }

    /**
     * @return string[]|null
     */
    private function resolveArrayFilterFromValue(mixed $value): ?array
    {
        if (is_string($value) && str_contains($value, ',')) {
            $value = explode(',', $value);
        }

        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if (is_array($value)) {
            $filtered = array_filter(
                Arr::flatten($value),
                fn ($v) => $v !== null && $v !== '',
            );

            return $filtered !== []
                ? array_map(static fn ($item) => (string) $item, array_values($filtered))
                : null;
        }

        return [(string) $value];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveBulkTransactionIds(Request $request, Ledger $ledger, array $validated): array
    {
        if (! $request->boolean('apply_to_all_matching')) {
            return $validated['ids'] ?? [];
        }

        $filters = $this->normalizeFilters((array) $request->input('filters', []));
        $excludedIds = array_map('intval', $request->input('excluded_ids', []));

        $query = $ledger->transactions();

        $this->applyTransactionFilters($query, $filters);

        if ($excludedIds !== []) {
            $query->whereNotIn('id', $excludedIds);
        }

        return $query->pluck('id')->all();
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

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function resolveValidatedInlinePayee(array $validated, Ledger $ledger): array
    {
        $newPayeeName = trim((string) ($validated['new_payee_name'] ?? ''));

        if ($newPayeeName === '' || ! empty($validated['payee_id'])) {
            return $validated;
        }

        $payee = $ledger->payees()->create(['name' => $newPayeeName]);
        $validated['payee_id'] = $payee->id;

        return $validated;
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

    private function normalizeTransferPairRelations(Ledger $ledger, LengthAwarePaginator $transactions): void
    {
        $visibleTransfers = $transactions->getCollection()
            ->filter(fn (Transaction $transaction) => $transaction->transfer_pair_id !== null)
            ->values();

        if ($visibleTransfers->isEmpty()) {
            return;
        }

        $pairIds = $visibleTransfers
            ->pluck('transfer_pair_id')
            ->filter()
            ->unique()
            ->values();

        $allPairTransactions = Transaction::query()
            ->where('ledger_id', $ledger->id)
            ->whereIn('transfer_pair_id', $pairIds)
            ->with('account')
            ->get()
            ->groupBy('transfer_pair_id');

        foreach ($visibleTransfers as $transaction) {
            $counterpart = $allPairTransactions
                ->get($transaction->transfer_pair_id)?->firstWhere('id', '!=', $transaction->id);

            if ($counterpart instanceof Transaction) {
                $transaction->setRelation('transferPair', $counterpart);
            }
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

    /**
     * @param  Collection<int, Category>  $categories
     * @return array<int, Category>
     */
    private function flattenCategories($categories): array
    {
        $flatCategories = [];

        $parentCategories = $categories->whereNull('parent_id')->values();

        foreach ($parentCategories as $parent) {
            $flatCategories[] = $parent;

            foreach ($categories->where('parent_id', $parent->id)->values() as $child) {
                $flatCategories[] = $child;
            }
        }

        return $flatCategories;
    }
}
