<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderRequest;
use App\Http\Requests\StoreAccountTypeRequest;
use App\Models\AccountType;
use App\Models\Ledger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountTypeController extends Controller
{
    public function index(Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        $accountTypes = $ledger->accountTypes()
            ->orderBy('position')
            ->get();

        return response()->json(['data' => $accountTypes]);
    }

    public function store(StoreAccountTypeRequest $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        $accountType = $ledger->accountTypes()->create([
            ...$request->validated(),
            'position' => $ledger->accountTypes()->count() + 1,
        ]);

        return response()->json(['data' => $accountType], 201);
    }

    public function show(Ledger $ledger, AccountType $accountType): JsonResponse
    {
        $this->authorize('view', $ledger);

        return response()->json(['data' => $accountType]);
    }

    public function update(Request $request, Ledger $ledger, AccountType $accountType): JsonResponse
    {
        $this->authorize('update', $ledger);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_credit' => ['boolean'],
        ]);

        $accountType->update($validated);

        return response()->json(['data' => $accountType->fresh()]);
    }

    public function destroy(Ledger $ledger, AccountType $accountType): JsonResponse
    {
        $this->authorize('delete', $ledger);

        if ($accountType->accounts()->exists()) {
            return response()->json([
                'message' => 'Cannot delete account type that has accounts.',
                'errors' => ['account_type' => ['Cannot delete account type that has accounts.']],
            ], 422);
        }

        $accountType->delete();

        return response()->json(null, 204);
    }

    public function reorder(ReorderRequest $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('update', $ledger);

        foreach ($request->items as $item) {
            $ledger->accountTypes()->where('id', $item['id'])->update(['position' => $item['position']]);
        }

        return response()->json(null, 204);
    }
}
