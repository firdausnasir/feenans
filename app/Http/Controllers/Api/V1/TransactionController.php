<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkUpdateTransactionsRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Http\Resources\AttachmentResource;
use App\Http\Resources\TransactionResource;
use App\Models\Account;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\Transaction;
use App\Services\TransactionService;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    public function __construct(private readonly TransactionService $transactionService) {}

    public function index(Request $request, Ledger $ledger): AnonymousResourceCollection
    {
        $this->authorize('view', $ledger);

        $query = $ledger->transactions()
            ->with(['account', 'category', 'payee', 'tags'])
            ->withCount('splits')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        $this->applyFilters($request, $query);

        return TransactionResource::collection(
            $query->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Ledger $ledger, Transaction $transaction): TransactionResource
    {
        $this->authorize('view', $ledger);

        return new TransactionResource(
            $transaction->load([
                'account',
                'category',
                'payee',
                'tags',
                'splits.category',
                'splits.payee',
                'attachments',
                'transferPair',
            ])
        );
    }

    public function store(StoreTransactionRequest $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        if ($request->string('transaction_type')->value() === TransactionType::Transfer->value) {
            $transactions = $this->transactionService->storeTransfer($ledger, [
                'from_account' => Account::query()->findOrFail($request->integer('account_id')),
                'to_account' => Account::query()->findOrFail($request->integer('to_account_id')),
                'amount' => $request->input('amount'),
                'description' => $request->input('description'),
                'notes' => $request->input('notes'),
                'transaction_date' => $request->date('transaction_date')?->toDateString() ?? now()->toDateString(),
            ]);

            $outgoing = $transactions[0]->load(['account', 'category', 'payee', 'tags']);

            return (new TransactionResource($outgoing))
                ->response()
                ->setStatusCode(201);
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

        return (new TransactionResource($transaction->load(['account', 'category', 'payee', 'tags'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateTransactionRequest $request, Ledger $ledger, Transaction $transaction): TransactionResource
    {
        $this->authorize('update', $ledger);

        $data = $request->validated();
        $newType = TransactionType::from($data['transaction_type']);
        $wasTransfer = $transaction->transfer_pair_id !== null;
        $isNowTransfer = $newType === TransactionType::Transfer;

        if ($wasTransfer && ! $isNowTransfer) {
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
            $this->transactionService->convertSingleToTransfer($transaction, $ledger, [
                'from_account' => Account::query()->findOrFail($data['account_id']),
                'to_account' => Account::query()->findOrFail($data['to_account_id']),
                'amount' => $data['amount'],
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'transaction_date' => $data['transaction_date'],
            ]);
        } elseif ($isNowTransfer) {
            $data['account'] = Account::query()->findOrFail($data['account_id']);
            $data['to_account'] = Account::query()->findOrFail($data['to_account_id']);
            $this->transactionService->update($transaction, $data);
        } else {
            $data['account'] = Account::query()->findOrFail($data['account_id']);
            $data['category'] = isset($data['category_id']) ? Category::query()->find($data['category_id']) : null;
            $data['payee'] = isset($data['payee_id']) ? Payee::query()->find($data['payee_id']) : null;
            $this->transactionService->update($transaction, $data);
        }

        $transaction->tags()->sync($data['tag_ids'] ?? []);

        return new TransactionResource(
            $transaction->fresh(['account', 'category', 'payee', 'tags'])
        );
    }

    public function destroy(Ledger $ledger, Transaction $transaction): JsonResponse
    {
        $this->authorize('delete', $ledger);

        $this->transactionService->delete($transaction);

        return response()->json(null, 204);
    }

    public function bulkUpdate(BulkUpdateTransactionsRequest $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('update', $ledger);

        $validated = $request->validated();

        $field = match ($validated['action']) {
            'change_category' => 'category_id',
            'change_account' => 'account_id',
            'change_payee' => 'payee_id',
        };

        DB::transaction(function () use ($validated, $ledger, $field): void {
            $ledger->transactions()
                ->whereIn('id', $validated['ids'])
                ->whereNull('transfer_pair_id')
                ->update([$field => $validated['value']]);
        });

        return response()->json(['message' => 'Transactions updated successfully.']);
    }

    public function bulkDestroy(Request $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('delete', $ledger);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer'],
        ]);

        DB::transaction(function () use ($validated, $ledger): void {
            $processedPairIds = [];
            foreach ($validated['ids'] as $id) {
                $transaction = $ledger->transactions()->findOrFail($id);
                if ($transaction->transfer_pair_id !== null) {
                    if (in_array($transaction->transfer_pair_id, $processedPairIds)) {
                        continue;
                    }
                    $processedPairIds[] = $transaction->transfer_pair_id;
                }
                $this->transactionService->delete($transaction);
            }
        });

        return response()->json(null, 204);
    }

    public function selectAll(Request $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        $query = $ledger->transactions();

        $this->applyFilters($request, $query);

        return response()->json(['ids' => $query->pluck('id')]);
    }

    public function storeAttachment(Request $request, Ledger $ledger, Transaction $transaction): JsonResponse
    {
        $this->authorize('view', $ledger);

        if ($request->hasFile('file') && ! $request->hasFile('attachments')) {
            $request->merge(['attachments' => [$request->file('file')]]);
        }

        $validated = $request->validate([
            'attachments' => ['required', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,gif,webp'],
        ]);

        $uploaded = [];

        foreach ($validated['attachments'] as $file) {
            $path = $file->store("attachments/{$ledger->id}");

            $uploaded[] = new AttachmentResource(
                $transaction->attachments()->create([
                    'filename' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                    'size' => $file->getSize(),
                ])
            );
        }

        return response()->json(['attachments' => $uploaded], Response::HTTP_CREATED);
    }

    public function showAttachment(Request $request, Ledger $ledger, Transaction $transaction, Attachment $attachment): StreamedResponse
    {
        $this->authorize('view', $ledger);

        return Storage::response($attachment->path, $attachment->filename, [
            'Content-Type' => $attachment->mime_type,
        ]);
    }

    public function destroyAttachment(Request $request, Ledger $ledger, Transaction $transaction, Attachment $attachment): JsonResponse
    {
        $this->authorize('delete', $ledger);

        Storage::delete($attachment->path);
        $attachment->delete();

        return response()->json(null, 204);
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

        $this->applyFilters($request, $query, skipDates: true);

        $filename = 'transactions-'.$dateFrom.'-'.$dateTo.'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Date', 'Description', 'Type', 'Account', 'Category', 'Payee', 'Amount', 'Notes']);

            $query->chunk(500, function ($transactions) use ($handle): void {
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

    public function summary(Request $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        $dateFrom = $request->date('date_from');
        $dateTo = $request->date('date_to');

        if ($dateFrom === null || $dateTo === null) {
            return response()->json(['message' => 'date_from and date_to are required.'], 422);
        }

        $dateFromStr = $dateFrom->toDateString();
        $dateToStr = $dateTo->toDateString();

        $cycleTransactions = $ledger->transactions()
            ->whereBetween('transaction_date', [$dateFromStr, $dateToStr]);

        $income = (float) (clone $cycleTransactions)
            ->where('transaction_type', TransactionType::Income->value)
            ->sum('amount');

        $expense = (float) (clone $cycleTransactions)
            ->where('transaction_type', TransactionType::Expense->value)
            ->sum('amount');

        // Previous period: same length period immediately before date_from
        $periodLength = $dateFrom->diffInDays($dateTo);
        $prevFromStr = $dateFrom->copy()->subDays($periodLength + 1)->toDateString();
        $prevToStr = $dateFrom->copy()->subDay()->toDateString();

        $prevTransactions = $ledger->transactions()
            ->whereBetween('transaction_date', [$prevFromStr, $prevToStr]);

        $prevIncome = (float) (clone $prevTransactions)
            ->where('transaction_type', TransactionType::Income->value)
            ->sum('amount');

        $prevExpense = (float) (clone $prevTransactions)
            ->where('transaction_type', TransactionType::Expense->value)
            ->sum('amount');

        return response()->json([
            'income' => round($income, 2),
            'expense' => round($expense, 2),
            'net' => round($income + $expense, 2),
            'prev_income' => round($prevIncome, 2),
            'prev_expense' => round($prevExpense, 2),
        ]);
    }

    public function dailyTrend(Request $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        $dateFrom = $request->date('date_from');
        $dateTo = $request->date('date_to');

        if ($dateFrom === null || $dateTo === null) {
            return response()->json(['message' => 'date_from and date_to are required.'], 422);
        }

        $dateFromStr = $dateFrom->toDateString();
        $dateToStr = $dateTo->toDateString();

        $query = $ledger->transactions()
            ->whereBetween('transaction_date', [$dateFromStr, $dateToStr]);

        if ($request->boolean('exclude_uncategorized')) {
            $query->whereNotNull('category_id');
        }

        $dailyTotals = $query
            ->select(
                'transaction_date',
                'transaction_type',
                DB::raw('SUM(amount) as total'),
            )
            ->groupBy('transaction_date', 'transaction_type')
            ->get()
            ->groupBy(fn ($row) => $row->transaction_date->format('Y-m-d'));

        $trendEnd = $dateTo->isBefore(now()) ? $dateTo : now();
        $period = CarbonPeriod::create($dateFrom, $trendEnd);

        $data = collect($period)->map(function ($day) use ($dailyTotals) {
            $key = $day->format('Y-m-d');
            $dayData = $dailyTotals->get($key, collect());

            $dayExpense = (float) ($dayData
                ->firstWhere('transaction_type', TransactionType::Expense)
                ?->total ?? 0);

            $dayIncome = (float) ($dayData
                ->firstWhere('transaction_type', TransactionType::Income)
                ?->total ?? 0);

            return [
                'date' => $key,
                'expense' => round(abs($dayExpense), 2),
                'income' => round($dayIncome, 2),
            ];
        })->values()->all();

        return response()->json(['data' => $data]);
    }

    public function uncategorizedCount(Request $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        $query = $ledger->transactions()
            ->whereNull('category_id')
            ->where('transaction_type', '!=', TransactionType::Transfer);

        if ($request->filled('date_from')) {
            $query->where('transaction_date', '>=', $request->date('date_from')->toDateString());
        }

        if ($request->filled('date_to')) {
            $query->where('transaction_date', '<=', $request->date('date_to')->toDateString());
        }

        return response()->json(['count' => $query->count()]);
    }

    /**
     * Apply common filters to a transaction query.
     *
     * @param  Builder|HasMany  $query
     */
    private function applyFilters(Request $request, mixed $query, bool $skipDates = false): void
    {
        if (! $skipDates) {
            if ($request->filled('date_from')) {
                $query->where('transaction_date', '>=', $request->date('date_from'));
            }

            if ($request->filled('date_to')) {
                $query->where('transaction_date', '<=', $request->date('date_to'));
            }
        }

        if ($request->filled('bill_id')) {
            $query->where('bill_id', $request->get('bill_id'));
        }

        $accountIds = $this->resolveArrayFilter($request, 'account_ids', 'account_id');
        if ($accountIds !== null) {
            $query->whereIn('account_id', $accountIds);
        }

        $categoryIds = $this->resolveArrayFilter($request, 'category_ids', 'category_id');
        if ($categoryIds !== null) {
            $query->whereIn('category_id', $categoryIds);
        }

        if ($request->boolean('uncategorized')) {
            $query->whereNull('category_id')->where('transaction_type', '!=', TransactionType::Transfer->value);
        }

        $transactionTypes = $this->resolveArrayFilter($request, 'transaction_types', 'transaction_type');
        if ($transactionTypes !== null) {
            $query->whereIn('transaction_type', $transactionTypes);
        }

        $payeeIds = $this->resolveArrayFilter($request, 'payee_ids', 'payee_id');
        if ($payeeIds !== null) {
            $query->whereIn('payee_id', $payeeIds);
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search): void {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $tagIds = $this->resolveArrayFilter($request, 'tag_ids');
        if ($tagIds !== null) {
            $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $tagIds));
        }
    }

    /**
     * Resolve an array filter from the request with backward compatibility for singular params.
     *
     * @return string[]|null
     */
    private function resolveArrayFilter(Request $request, string $key, ?string $singularKey = null): ?array
    {
        $value = $request->input($key);

        if ($value === null || $value === '' || $value === []) {
            // Fall back to singular key for backward compatibility
            if ($singularKey !== null && $request->filled($singularKey)) {
                return [(string) $request->input($singularKey)];
            }

            return null;
        }

        if (is_array($value)) {
            $filtered = array_filter($value, fn ($v) => $v !== null && $v !== '');

            return $filtered !== [] ? array_values($filtered) : null;
        }

        return [(string) $value];
    }
}
