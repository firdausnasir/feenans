<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Account;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransactionController extends Controller
{
    public function __construct(private readonly TransactionService $transactionService) {}

    public function index(Request $request, Ledger $ledger): AnonymousResourceCollection
    {
        $this->authorize('view', $ledger);

        $query = $ledger->transactions()
            ->with(['account', 'category', 'payee', 'tags'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->integer('account_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->string('transaction_type')->value());
        }

        if ($request->filled('payee_id')) {
            $query->where('payee_id', $request->integer('payee_id'));
        }

        if ($request->filled('date_from')) {
            $query->where('transaction_date', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('transaction_date', '<=', $request->date('date_to'));
        }

        return TransactionResource::collection(
            $query->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Ledger $ledger, Transaction $transaction): TransactionResource
    {
        $this->authorize('view', $ledger);

        return new TransactionResource(
            $transaction->load(['account', 'category', 'payee', 'tags'])
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
}
