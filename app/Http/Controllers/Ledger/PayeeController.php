<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\MergePayeesRequest;
use App\Http\Requests\UpdatePayeeRequest;
use App\Models\Ledger;
use App\Models\Payee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PayeeController extends Controller
{
    public function index(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/payees/index');
    }

    public function store(Request $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('view', $ledger);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $ledger->payees()->create($validated);

        return back();
    }

    public function update(UpdatePayeeRequest $request, Ledger $ledger, Payee $payee): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $payee->update($request->validated());

        return back();
    }

    public function merge(MergePayeesRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $sourcePayee = $ledger->payees()->findOrFail($request->integer('source_id'));
        $targetPayee = $ledger->payees()->findOrFail($request->integer('target_id'));

        DB::transaction(function () use ($sourcePayee, $targetPayee): void {
            $sourcePayee->transactions()->update(['payee_id' => $targetPayee->id]);
            $sourcePayee->delete();
        });

        return back();
    }

    public function destroy(Request $request, Ledger $ledger, Payee $payee): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $payee->delete();

        return back();
    }
}
