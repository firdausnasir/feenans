<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayeeResource;
use App\Models\Ledger;
use App\Models\Payee;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PayeeController extends Controller
{
    public function index(Ledger $ledger): AnonymousResourceCollection
    {
        $this->authorize('view', $ledger);

        $payees = $ledger->payees()
            ->orderBy('name')
            ->get();

        return PayeeResource::collection($payees);
    }

    public function show(Ledger $ledger, Payee $payee): PayeeResource
    {
        $this->authorize('view', $ledger);

        return new PayeeResource($payee);
    }
}
