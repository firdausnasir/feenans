<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayBillRequest;
use App\Http\Requests\StoreBillRequest;
use App\Http\Requests\UpdateBillRequest;
use App\Http\Resources\BillResource;
use App\Http\Resources\TransactionResource;
use App\Models\Bill;
use App\Models\Ledger;
use App\Services\BillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BillController extends Controller
{
    public function __construct(private readonly BillService $billService) {}

    public function index(Request $request, Ledger $ledger): AnonymousResourceCollection|JsonResponse
    {
        $this->authorize('view', $ledger);

        if ($request->boolean('upcoming')) {
            $groups = $this->billService->getUpcomingBills($ledger);

            return response()->json([
                'upcoming' => BillResource::collection($groups['upcoming']),
                'due' => BillResource::collection($groups['due']),
                'missed' => BillResource::collection($groups['missed']),
            ]);
        }

        $query = $ledger->bills()->with(['account', 'category', 'payee']);

        if ($request->boolean('active_only')) {
            $query->active();
        }

        if ($request->boolean('with_transactions')) {
            $query->with([
                'transactions' => fn ($q) => $q->latest('transaction_date')->limit(5),
            ]);
        }

        $bills = $query
            ->oldest('next_due_date')
            ->get();

        if ($request->boolean('with_missed')) {
            $bills->each(function (Bill $bill): void {
                $bill->missed_cycles = $this->billService->computeMissedCycles($bill);
            });
        }

        return BillResource::collection($bills);
    }

    public function show(Ledger $ledger, Bill $bill): BillResource
    {
        $this->authorize('view', $ledger);

        return new BillResource($bill->load(['account', 'category', 'payee']));
    }

    public function store(StoreBillRequest $request, Ledger $ledger): BillResource
    {
        $this->authorize('view', $ledger);

        $bill = $this->billService->store($ledger, $request->validated());

        return new BillResource($bill->load(['account', 'category', 'payee']));
    }

    public function update(UpdateBillRequest $request, Ledger $ledger, Bill $bill): BillResource
    {
        $this->authorize('update', $ledger);

        $bill = $this->billService->update($bill, $request->validated());

        return new BillResource($bill->load(['account', 'category', 'payee']));
    }

    public function destroy(Ledger $ledger, Bill $bill): JsonResponse
    {
        $this->authorize('delete', $ledger);

        $bill->delete();

        return response()->json(null, 204);
    }

    public function pay(PayBillRequest $request, Ledger $ledger, Bill $bill): TransactionResource
    {
        $this->authorize('update', $ledger);

        $transaction = $this->billService->payBill($bill, $request->validated());

        return new TransactionResource($transaction->load(['account', 'category', 'payee']));
    }

    public function toggle(Ledger $ledger, Bill $bill): BillResource
    {
        $this->authorize('update', $ledger);

        $bill->update(['is_active' => ! $bill->is_active]);

        return new BillResource($bill->refresh()->load(['account', 'category', 'payee']));
    }
}
