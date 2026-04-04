<?php

namespace App\Http\Controllers\Api\V1\Ledger;

use App\Actions\Dashboard\Queries\GetDashboardPageQuery;
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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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

    public function dashboardSummary(
        Ledger $ledger,
        Request $request,
        GetDashboardPageQuery $getDashboardPage,
    ): JsonResponse {
        $this->authorize('view', $ledger);

        $offset = $request->integer('offset', 0);

        return response()->json([
            'data' => [
                'cycle' => $getDashboardPage->cycle($ledger, $offset),
                'summary' => $getDashboardPage->summary($ledger, $offset),
            ],
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function dashboardDailyTrend(
        Ledger $ledger,
        Request $request,
        GetDashboardPageQuery $getDashboardPage,
    ): JsonResponse {
        $this->authorize('view', $ledger);

        return response()->json([
            'data' => $getDashboardPage->dailyTrend($ledger, $request->integer('offset', 0)),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function dashboardRecent(
        Ledger $ledger,
        Request $request,
        GetDashboardPageQuery $getDashboardPage,
    ): JsonResponse {
        $this->authorize('view', $ledger);

        return response()->json([
            'data' => $getDashboardPage->recentTransactions($ledger, $request->integer('offset', 0)),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function show(Ledger $ledger, Transaction $transaction): JsonResponse
    {
        Gate::authorize('view', $ledger);

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

        return response()->json([
            'data' => ApiTransactionData::fromModel($transaction)->toArray(),
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

        return response()->json(status: 204);
    }

    public function bulkDestroy(
        Ledger $ledger,
        BulkDestroyTransactionsData $data,
        BulkDestroyTransactionsAction $bulkDestroyTransactions,
    ): JsonResponse {
        $bulkDestroyTransactions($data);

        return response()->json(status: 204);
    }

    public function selectAll(
        Ledger $ledger,
        SelectAllTransactionsData $data,
        SelectAllTransactionIdsQuery $selectAllTransactionIds,
    ): JsonResponse {
        return response()->json([
            'data' => [
                'ids' => $selectAllTransactionIds($ledger, $data->toFilterInput()),
            ],
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}
