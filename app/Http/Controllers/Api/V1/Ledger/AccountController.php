<?php

namespace App\Http\Controllers\Api\V1\Ledger;

use App\Actions\Accounts\Queries\GetNetWorthQuery;
use App\Actions\Accounts\Queries\ListAccountsByTotalsQuery;
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
use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{
    public function index(Ledger $ledger, ListAccountsByTotalsQuery $listAccounts): JsonResponse
    {
        $this->authorize('view', $ledger);

        $groups = $listAccounts($ledger);

        $accounts = $groups->flatMap(fn ($group) => $group->accounts)->values();

        return response()->json([
            'data' => $accounts->map->toArray()->values()->all(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function grouped(Ledger $ledger, ListAccountsByTotalsQuery $listAccounts): JsonResponse
    {
        $this->authorize('view', $ledger);

        return response()->json([
            'data' => $listAccounts($ledger)
                ->map(fn ($group) => $group->toArray())
                ->values()
                ->all(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function types(Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        return response()->json([
            'data' => $ledger->accountTypes()
                ->orderBy('position')
                ->get()
                ->map
                ->toArray()
                ->values()
                ->all(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function netWorth(Ledger $ledger, GetNetWorthQuery $getNetWorth): JsonResponse
    {
        $this->authorize('view', $ledger);

        return response()->json([
            'data' => $getNetWorth($ledger)->toArray(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function store(Ledger $ledger, StoreAccountData $data, StoreAccountAction $storeAccount): JsonResponse
    {
        return response()->json([
            'data' => $storeAccount($data)->toArray(),
        ], 201, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function update(Ledger $ledger, Account $account, UpdateAccountData $data, UpdateAccountAction $updateAccount): JsonResponse
    {
        return response()->json([
            'data' => $updateAccount($data)->toArray(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function destroy(Ledger $ledger, Account $account, DeleteAccountAction $deleteAccount): JsonResponse
    {
        $this->authorize('delete', $ledger);

        return response()->json([
            'data' => $deleteAccount($account)->toArray(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function reorder(Ledger $ledger, ReorderAccountsData $data, ReorderAccountsAction $reorderAccounts): JsonResponse
    {
        $reorderAccounts($data);

        return response()->json(status: 204);
    }

    public function adjustBalance(Ledger $ledger, Account $account, AdjustAccountBalanceData $data, AdjustAccountBalanceAction $adjustBalance): JsonResponse
    {
        $adjustBalance($data);

        return response()->json(status: 204);
    }
}
