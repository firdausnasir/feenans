<?php

namespace App\Http\Controllers\Ledger;

use App\Actions\Payees\UseCases\DeletePayeeAction;
use App\Actions\Payees\UseCases\StorePayeeAction;
use App\Actions\Payees\UseCases\UpdatePayeeAction;
use App\Data\Payees\Input\StorePayeeData;
use App\Data\Payees\Input\UpdatePayeeData;
use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Models\Payee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayeeController extends Controller
{
    public function index(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        $search = $request->string('search')->toString();

        return Inertia::render('ledgers/payees/index', [
            'search' => $search,
        ]);
    }

    public function store(Ledger $ledger, StorePayeeData $data, StorePayeeAction $storePayee): RedirectResponse
    {
        $storePayee($data);

        return back()->with('success', 'Payee added.');
    }

    public function update(Ledger $ledger, Payee $payee, UpdatePayeeData $data, UpdatePayeeAction $updatePayee): RedirectResponse
    {
        $updatePayee($data);

        return back()->with('success', 'Payee updated.');
    }

    public function destroy(Ledger $ledger, Payee $payee, DeletePayeeAction $deletePayee): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $deletePayee($payee);

        return back()->with('success', 'Payee deleted.');
    }
}
