<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Models\Ledger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountController extends Controller
{
    public function index(Ledger $ledger): AnonymousResourceCollection
    {
        $this->authorize('view', $ledger);

        $accounts = $ledger->accounts()
            ->with('accountType')
            ->orderBy('name')
            ->get();

        return AccountResource::collection($accounts);
    }

    public function show(Ledger $ledger, Account $account): AccountResource
    {
        $this->authorize('view', $ledger);

        return new AccountResource($account->load('accountType'));
    }

    public function store(StoreAccountRequest $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        $account = $ledger->accounts()->create($request->validated());

        return (new AccountResource($account->load('accountType')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateAccountRequest $request, Ledger $ledger, Account $account): AccountResource
    {
        $this->authorize('update', $ledger);

        $account->update($request->validated());

        return new AccountResource($account->fresh('accountType'));
    }

    public function destroy(Ledger $ledger, Account $account): JsonResponse
    {
        $this->authorize('delete', $ledger);

        $account->delete();

        return response()->json(null, 204);
    }
}
