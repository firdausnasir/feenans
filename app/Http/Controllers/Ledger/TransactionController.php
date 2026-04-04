<?php

namespace App\Http\Controllers\Ledger;

use App\Actions\Transactions\Queries\ExportTransactionsQuery;
use App\Actions\Transactions\Queries\GetTransactionIndexPageQuery;
use App\Actions\Transactions\Queries\SelectAllTransactionIdsQuery;
use App\Actions\Transactions\UseCases\BulkDestroyTransactionsAction;
use App\Actions\Transactions\UseCases\BulkUpdateTransactionsAction;
use App\Actions\Transactions\UseCases\DeleteTransactionAction;
use App\Actions\Transactions\UseCases\StoreTransactionAction;
use App\Actions\Transactions\UseCases\UpdateTransactionAction;
use App\Data\Transactions\Input\BulkDestroyTransactionsData;
use App\Data\Transactions\Input\BulkUpdateTransactionsData;
use App\Data\Transactions\Input\ExportTransactionsData;
use App\Data\Transactions\Input\GetTransactionIndexData;
use App\Data\Transactions\Input\SelectAllTransactionsData;
use App\Data\Transactions\Input\StoreTransactionData;
use App\Data\Transactions\Input\UpdateTransactionData;
use App\Data\Transactions\Output\Web\TransactionIndexPageData;
use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    public function index(
        Ledger $ledger,
        GetTransactionIndexData $input,
        GetTransactionIndexPageQuery $getTransactionIndexPage,
    ): Response {
        $resolved = null;
        $resolve = function () use ($input, $getTransactionIndexPage, &$resolved): TransactionIndexPageData {
            return $resolved ??= $getTransactionIndexPage($input->ledger, $input);
        };

        return Inertia::render('ledgers/transactions/index', [
            'filters' => fn () => $resolve()->filters->toArray(),
            'accounts' => fn () => $resolve()->accounts,
            'categories' => fn () => $resolve()->categories,
            'payees' => fn () => $resolve()->payees,
            'tags' => fn () => $resolve()->tags,
        ]);
    }

    public function store(
        Ledger $ledger,
        StoreTransactionData $data,
        StoreTransactionAction $storeTransaction,
    ): RedirectResponse {
        $storeTransaction($data);

        return back();
    }

    public function edit(
        Ledger $ledger,
        Transaction $transaction,
    ): Response {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/transactions/edit', [
            'transaction_id' => $transaction->id,
        ]);
    }

    public function export(
        Ledger $ledger,
        ExportTransactionsData $data,
        ExportTransactionsQuery $exportTransactions,
    ): StreamedResponse {
        return $exportTransactions($ledger, $data);
    }

    public function update(
        Ledger $ledger,
        Transaction $transaction,
        UpdateTransactionData $data,
        UpdateTransactionAction $updateTransaction,
    ): RedirectResponse {
        $updateTransaction($data);

        return redirect()
            ->route('ledgers.transactions.index', $ledger)
            ->with('success', 'Transaction updated.');
    }

    public function destroy(
        Ledger $ledger,
        Transaction $transaction,
        DeleteTransactionAction $deleteTransaction,
    ): RedirectResponse {
        $this->authorize('delete', $ledger);

        $deleteTransaction($transaction);

        return redirect()
            ->route('ledgers.transactions.index', $ledger)
            ->with('success', 'Transaction deleted.');
    }

    public function bulkUpdate(
        Ledger $ledger,
        BulkUpdateTransactionsData $data,
        BulkUpdateTransactionsAction $bulkUpdateTransactions,
    ): RedirectResponse {
        $bulkUpdateTransactions($data);

        return back()->with('success', 'Transactions updated.');
    }

    public function bulkDestroy(
        Ledger $ledger,
        BulkDestroyTransactionsData $data,
        BulkDestroyTransactionsAction $bulkDestroyTransactions,
    ): RedirectResponse {
        $bulkDestroyTransactions($data);

        return back()->with('success', 'Transactions deleted.');
    }

    public function selectAll(
        Ledger $ledger,
        SelectAllTransactionsData $data,
        SelectAllTransactionIdsQuery $selectAllTransactionIds,
    ): JsonResponse {
        return response()->json([
            'ids' => $selectAllTransactionIds($data->ledger, $data->toFilterInput()),
        ]);
    }
}
