<?php

namespace App\Http\Controllers\Api\V1\Ledger;

use App\Actions\Transactions\Queries\ListTransactionsQuery;
use App\Actions\Transactions\Queries\NormalizeTransactionFiltersQuery;
use App\Actions\Transactions\Queries\SelectAllTransactionIdsQuery;
use App\Actions\Transactions\UseCases\BulkDestroyTransactionsAction;
use App\Actions\Transactions\UseCases\BulkUpdateTransactionsAction;
use App\Actions\Transactions\UseCases\DeleteTransactionAction;
use App\Actions\Transactions\UseCases\StoreTransactionAction;
use App\Actions\Transactions\UseCases\UpdateTransactionAction;
use App\Data\Transactions\Input\BulkDestroyTransactionsData;
use App\Data\Transactions\Input\BulkUpdateTransactionsData;
use App\Data\Transactions\Input\GetTransactionIndexData;
use App\Data\Transactions\Input\SelectAllTransactionsData;
use App\Data\Transactions\Input\StoreTransactionData;
use App\Data\Transactions\Input\UpdateTransactionData;
use App\Data\Transactions\Output\Api\TransactionData as ApiTransactionData;
use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;

class TransactionController extends Controller
{
    public function index(
        Ledger $ledger,
        GetTransactionIndexData $data,
        NormalizeTransactionFiltersQuery $normalizeFilters,
        ListTransactionsQuery $listTransactions,
    ): JsonResponse {
        $filters = $normalizeFilters($data);
        $transactions = $listTransactions($ledger, $filters, $data->page, $data->per_page);

        return response()->json([
            'data' => $transactions->items(),
            'meta' => [
                'filters' => $filters->toArray(),
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'next_page_url' => $transactions->nextPageUrl(),
                'prev_page_url' => $transactions->previousPageUrl(),
            ],
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function store(Ledger $ledger, StoreTransactionData $data, StoreTransactionAction $storeTransaction): JsonResponse
    {
        return response()->json([
            'data' => ApiTransactionData::fromModel($storeTransaction($data))->toArray(),
        ], 201, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function update(
        Ledger $ledger,
        Transaction $transaction,
        UpdateTransactionData $data,
        UpdateTransactionAction $updateTransaction,
    ): JsonResponse {
        return response()->json([
            'data' => ApiTransactionData::fromModel($updateTransaction($data))->toArray(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function destroy(Ledger $ledger, Transaction $transaction, DeleteTransactionAction $deleteTransaction): JsonResponse
    {
        $this->authorize('delete', $ledger);

        return response()->json([
            'data' => ApiTransactionData::fromModel($deleteTransaction($transaction))->toArray(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function bulkUpdate(
        Ledger $ledger,
        BulkUpdateTransactionsData $data,
        BulkUpdateTransactionsAction $bulkUpdateTransactions,
    ): JsonResponse {
        $bulkUpdateTransactions($data);

        return response()->json();
    }

    public function bulkDestroy(
        Ledger $ledger,
        BulkDestroyTransactionsData $data,
        BulkDestroyTransactionsAction $bulkDestroyTransactions,
    ): JsonResponse {
        $bulkDestroyTransactions($data);

        return response()->json();
    }

    public function selectAll(
        Ledger $ledger,
        SelectAllTransactionsData $data,
        SelectAllTransactionIdsQuery $selectAllTransactionIds,
    ): JsonResponse {
        return response()->json([
            'ids' => $selectAllTransactionIds($ledger, $data->toFilterInput()),
        ]);
    }
}
