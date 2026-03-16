<?php

namespace App\Http\Controllers\Ledger;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkUpdateTransactionsRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
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
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactionService,
        private readonly BudgetService $budgetService,
    ) {}

    public function index(Request $request, Ledger $ledger): Response|JsonResponse
    {
        $this->authorize('view', $ledger);

        $query = $ledger->transactions()
            ->with(['account', 'category', 'payee', 'transferPair', 'tags', 'attachments', 'splits.category', 'splits.payee'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        if ($request->filled('bill_id')) {
            $query->where('bill_id', $request->get('bill_id'));
        }

        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        if ($dateFrom && $dateTo) {
            $query->whereBetween('transaction_date', [$dateFrom, $dateTo]);
        } elseif ($dateFrom) {
            $query->where('transaction_date', '>=', $dateFrom);
        } elseif ($dateTo) {
            $query->where('transaction_date', '<=', $dateTo);
        }

        $accountIds = $this->resolveArrayFilter($request, 'account_ids');
        if ($accountIds !== null) {
            $query->whereIn('account_id', $accountIds);
        }

        $categoryIds = $this->resolveArrayFilter($request, 'category_ids');
        if ($categoryIds !== null) {
            $query->whereIn('category_id', $categoryIds);
        }

        if ($request->boolean('uncategorized')) {
            $query->whereNull('category_id')->where('transaction_type', '!=', 'transfer');
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

        $tagIds = $this->resolveArrayFilter($request, 'tag_ids');
        if ($tagIds !== null) {
            $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $tagIds));
        }

        if ($request->wantsJson()) {
            return response()->json([
                'transactions' => $query->paginate(25)->withQueryString(),
            ]);
        }

        return Inertia::render('ledgers/transactions/index', [
            'ledger' => $ledger,
            'transactions' => $query->paginate(25)->withQueryString(),
            'accounts' => $ledger->accounts()->visible()->with('accountType')->orderBy('name')->get(),
            'categories' => $ledger->categories()->with('children')->parents()->orderBy('position')->get(),
            'payees' => $ledger->payees()->orderBy('name')->get(),
            'tags' => $ledger->tags()->orderBy('name')->get(),
            'filters' => [
                'date_from' => $dateFrom ?? '',
                'date_to' => $dateTo ?? '',
                'account_ids' => $accountIds ?? [],
                'category_ids' => $categoryIds ?? [],
                'transaction_types' => $transactionTypes ?? [],
                'payee_ids' => $payeeIds ?? [],
                'tag_ids' => $tagIds ?? [],
                'search' => $request->get('search'),
                'bill_id' => $request->get('bill_id'),
                'uncategorized' => $request->boolean('uncategorized') ? '1' : null,
            ],
        ]);
    }

    public function store(StoreTransactionRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('view', $ledger);

        $this->resolveInlinePayee($request, $ledger);

        if ($request->string('transaction_type')->value() === TransactionType::Transfer->value) {
            $this->transactionService->storeTransfer($ledger, [
                'from_account' => Account::query()->findOrFail($request->integer('account_id')),
                'to_account' => Account::query()->findOrFail($request->integer('to_account_id')),
                'amount' => $request->input('amount'),
                'description' => $request->input('description'),
                'notes' => $request->input('notes'),
                'transaction_date' => $request->date('transaction_date')?->toDateString() ?? now()->toDateString(),
            ]);

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

    public function edit(Request $request, Ledger $ledger, Transaction $transaction): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/transactions/edit', [
            'ledger' => $ledger,
            'transaction' => $transaction->load(['account', 'category', 'payee', 'transferPair', 'tags', 'attachments', 'splits.category', 'splits.payee']),
            'accounts' => $ledger->accounts()
                ->where(fn ($q) => $q->visible()->orWhere('id', $transaction->account_id))
                ->with('accountType')
                ->orderBy('name')
                ->get(),
            'categories' => $ledger->categories()->with('children')->parents()->orderBy('position')->get(),
            'payees' => $ledger->payees()->orderBy('name')->get(),
            'tags' => $ledger->tags()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateTransactionRequest $request, Ledger $ledger, Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $this->resolveInlinePayee($request, $ledger);

        $data = $request->validated();
        $newType = TransactionType::from($data['transaction_type']);
        $wasTransfer = $transaction->transfer_pair_id !== null;
        $isNowTransfer = $newType === TransactionType::Transfer;

        if ($wasTransfer && ! $isNowTransfer) {
            // Converting transfer → expense/income: delete the pair, update this transaction
            $this->transactionService->convertTransferToSingle($transaction, [
                'account' => Account::query()->findOrFail($data['account_id']),
                'category' => isset($data['category_id']) ? Category::query()->find($data['category_id']) : null,
                'payee' => isset($data['payee_id']) ? Payee::query()->find($data['payee_id']) : null,
                'transaction_type' => $newType,
                'amount' => $data['amount'],
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'transaction_date' => $data['transaction_date'],
            ]);
        } elseif (! $wasTransfer && $isNowTransfer) {
            // Converting expense/income → transfer: create a paired transaction
            $this->transactionService->convertSingleToTransfer($transaction, $ledger, [
                'from_account' => Account::query()->findOrFail($data['account_id']),
                'to_account' => Account::query()->findOrFail($data['to_account_id']),
                'amount' => $data['amount'],
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'transaction_date' => $data['transaction_date'],
            ]);
        } elseif ($isNowTransfer) {
            // Transfer → transfer: update both sides
            $data['account'] = Account::query()->findOrFail($data['account_id']);
            $data['to_account'] = Account::query()->findOrFail($data['to_account_id']);
            $this->transactionService->update($transaction, $data);
        } else {
            // expense/income → expense/income
            $data['account'] = Account::query()->findOrFail($data['account_id']);
            $data['category'] = isset($data['category_id']) ? Category::query()->find($data['category_id']) : null;
            $data['payee'] = isset($data['payee_id']) ? Payee::query()->find($data['payee_id']) : null;
            $this->transactionService->update($transaction, $data);
        }

        $transaction->tags()->sync($data['tag_ids'] ?? []);

        $this->budgetService->checkThresholds(
            $ledger,
            $data['category_id'] ?? null,
        );

        return to_route('ledgers.transactions.index', $ledger);
    }

    public function destroy(Request $request, Ledger $ledger, Transaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $this->transactionService->delete($transaction);

        return to_route('ledgers.transactions.index', $ledger);
    }

    public function trash(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/transactions/trash/index', [
            'ledger' => $ledger,
            'transactions' => $ledger->transactions()
                ->onlyTrashed()
                ->with(['account', 'category', 'payee'])
                ->orderByDesc('deleted_at')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function restore(Request $request, Ledger $ledger, Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $transaction->restore();

        return to_route('ledgers.transactions.trash', $ledger);
    }

    public function forceDestroy(Request $request, Ledger $ledger, Transaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $this->transactionService->forceDelete($transaction);

        return to_route('ledgers.transactions.trash', $ledger);
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

    public function bulkUpdate(BulkUpdateTransactionsRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $validated = $request->validated();

        $field = match ($validated['action']) {
            'change_category' => 'category_id',
            'change_account' => 'account_id',
            'change_payee' => 'payee_id',
        };

        DB::transaction(function () use ($validated, $ledger, $field) {
            $ledger->transactions()
                ->whereIn('id', $validated['ids'])
                ->whereNull('transfer_pair_id')
                ->update([$field => $validated['value']]);
        });

        return back();
    }

    public function bulkDestroy(Request $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer'],
        ]);

        DB::transaction(function () use ($validated, $ledger) {
            $processedPairIds = [];
            foreach ($validated['ids'] as $id) {
                $transaction = $ledger->transactions()->findOrFail($id);
                if ($transaction->transfer_pair_id !== null) {
                    if (in_array($transaction->transfer_pair_id, $processedPairIds)) {
                        continue; // already deleted via the pair
                    }
                    $processedPairIds[] = $transaction->transfer_pair_id;
                }
                $this->transactionService->delete($transaction);
            }
        });

        return back();
    }
}
