<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Ledger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillController extends Controller
{
    public function index(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/bills/index');
    }

    public function create(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/bills/create');
    }

    public function edit(Request $request, Ledger $ledger, Bill $bill): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/bills/edit', [
            'billId' => $bill->id,
        ]);
    }
}
