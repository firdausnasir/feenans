<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BillResource;
use App\Models\Bill;
use App\Models\Ledger;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BillController extends Controller
{
    public function index(Ledger $ledger): AnonymousResourceCollection
    {
        $this->authorize('view', $ledger);

        $bills = $ledger->bills()
            ->with(['account', 'category', 'payee'])
            ->get();

        return BillResource::collection($bills);
    }

    public function show(Ledger $ledger, Bill $bill): BillResource
    {
        $this->authorize('view', $ledger);

        return new BillResource($bill->load(['account', 'category', 'payee']));
    }
}
