<?php

namespace App\Http\Controllers\Api\V1\Ledger;

use App\Actions\Payees\Queries\ListPayeesQuery;
use App\Actions\Payees\UseCases\DeletePayeeAction;
use App\Actions\Payees\UseCases\StorePayeeAction;
use App\Actions\Payees\UseCases\UpdatePayeeAction;
use App\Data\Payees\Input\StorePayeeData;
use App\Data\Payees\Input\UpdatePayeeData;
use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Models\Payee;
use Illuminate\Http\JsonResponse;

class PayeeController extends Controller
{
    public function index(Ledger $ledger, ListPayeesQuery $listPayees): JsonResponse
    {
        $this->authorize('view', $ledger);

        return response()->json([
            'data' => $listPayees($ledger)->map->toArray()->values()->all(),
        ]);
    }

    public function store(Ledger $ledger, StorePayeeData $data, StorePayeeAction $storePayee): JsonResponse
    {
        return response()->json([
            'data' => $storePayee($data)->toArray(),
        ], 201);
    }

    public function update(Ledger $ledger, Payee $payee, UpdatePayeeData $data, UpdatePayeeAction $updatePayee): JsonResponse
    {
        return response()->json([
            'data' => $updatePayee($data)->toArray(),
        ]);
    }

    public function destroy(Ledger $ledger, Payee $payee, DeletePayeeAction $deletePayee): JsonResponse
    {
        $this->authorize('delete', $ledger);

        return response()->json([
            'data' => $deletePayee($payee)->toArray(),
        ]);
    }
}
