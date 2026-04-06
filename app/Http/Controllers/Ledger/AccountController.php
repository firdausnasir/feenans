<?php

namespace App\Http\Controllers\Ledger;

use App\Actions\Accounts\Queries\ExportAccountTransactionsQuery;
use App\Actions\Accounts\UseCases\AdjustAccountBalanceAction;
use App\Actions\Accounts\UseCases\DeleteAccountAction;
use App\Actions\Accounts\UseCases\ReorderAccountsAction;
use App\Actions\Accounts\UseCases\StoreAccountAction;
use App\Actions\Accounts\UseCases\UpdateAccountAction;
use App\Data\Accounts\Input\AdjustAccountBalanceData;
use App\Data\Accounts\Input\ReorderAccountsData;
use App\Data\Accounts\Input\StoreAccountData;
use App\Data\Accounts\Input\UpdateAccountData;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Ledger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountController extends Controller
{
    public function index(
        Ledger $ledger,
    ): Response {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/accounts/index');
    }

    public function store(Ledger $ledger, StoreAccountData $data, StoreAccountAction $storeAccount): RedirectResponse
    {
        $storeAccount($data);

        return back()->with('success', 'Account created.');
    }

    public function update(Ledger $ledger, Account $account, UpdateAccountData $data, UpdateAccountAction $updateAccount): RedirectResponse
    {
        $updateAccount($data);

        return back()->with('success', 'Account updated.');
    }

    public function destroy(Ledger $ledger, Account $account, DeleteAccountAction $deleteAccount): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $deleteAccount($account);

        return back()->with('success', 'Account deleted.');
    }

    public function reorder(Ledger $ledger, ReorderAccountsData $data, ReorderAccountsAction $reorderAccounts): RedirectResponse
    {
        $reorderAccounts($data);

        return back();
    }

    public function adjustBalance(Ledger $ledger, Account $account, AdjustAccountBalanceData $data, AdjustAccountBalanceAction $adjustBalance): RedirectResponse
    {
        $adjustBalance($data);

        return back()->with('success', 'Balance adjusted.');
    }

    public function export(Request $request, Ledger $ledger, Account $account, ExportAccountTransactionsQuery $exportQuery): StreamedResponse
    {
        $this->authorize('view', $ledger);

        return $exportQuery($account, $request->query('date_from'), $request->query('date_to'));
    }
}
