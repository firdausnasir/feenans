<?php

namespace App\Http\Controllers\Api\V1\Ledger;

use App\Actions\Bills\Queries\ListBillsQuery;
use App\Actions\Bills\UseCases\DeleteBillAction;
use App\Actions\Bills\UseCases\PayBillAction;
use App\Actions\Bills\UseCases\StoreBillAction;
use App\Actions\Bills\UseCases\ToggleBillAction;
use App\Actions\Bills\UseCases\UpdateBillAction;
use App\Actions\Dashboard\Queries\GetDashboardPageQuery;
use App\Data\Bills\Input\PayBillData;
use App\Data\Bills\Input\StoreBillData;
use App\Data\Bills\Input\UpdateBillData;
use App\Data\Bills\Output\Api\BillData as ApiBillData;
use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Ledger;
use Illuminate\Http\JsonResponse;

class BillController extends Controller
{
    public function index(Ledger $ledger, ListBillsQuery $listBills): JsonResponse
    {
        $this->authorize('view', $ledger);

        return response()->json([
            'data' => $listBills($ledger)->map->toArray()->values()->all(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function dashboardUpcoming(Ledger $ledger, GetDashboardPageQuery $getDashboardPage): JsonResponse
    {
        $this->authorize('view', $ledger);

        return response()->json([
            'data' => $getDashboardPage->upcomingBills($ledger),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function store(Ledger $ledger, StoreBillData $data, StoreBillAction $storeBill): JsonResponse
    {
        return response()->json([
            'data' => ApiBillData::fromModel($storeBill($data))->toArray(),
        ], 201, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function update(Ledger $ledger, Bill $bill, UpdateBillData $data, UpdateBillAction $updateBill): JsonResponse
    {
        return response()->json([
            'data' => ApiBillData::fromModel($updateBill($data))->toArray(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function destroy(Ledger $ledger, Bill $bill, DeleteBillAction $deleteBill): JsonResponse
    {
        $this->authorize('delete', $ledger);

        return response()->json([
            'data' => ApiBillData::fromModel($deleteBill($bill))->toArray(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function toggle(Ledger $ledger, Bill $bill, ToggleBillAction $toggleBill): JsonResponse
    {
        $this->authorize('update', $ledger);

        return response()->json([
            'data' => ApiBillData::fromModel($toggleBill($bill))->toArray(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function pay(Ledger $ledger, Bill $bill, PayBillData $data, PayBillAction $payBill): JsonResponse
    {
        $this->authorize('update', $ledger);

        $payBill($data);

        return response()->json([
            'data' => ApiBillData::fromModel($bill->fresh()->loadMissing(['account', 'toAccount', 'category', 'payee']))->toArray(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}
