<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayBillRequest;
use App\Http\Requests\StoreBillRequest;
use App\Http\Requests\UpdateBillRequest;
use App\Http\Resources\BillResource;
use App\Models\Bill;
use App\Models\Ledger;
use App\Services\BillService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillController extends Controller
{
    public function __construct(private readonly BillService $billService) {}

    public function index(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/bills/index', [
            'accounts' => fn () => $ledger->accounts()->visible()->orderBy('name')->get(['id', 'ledger_id', 'name', 'color']),
            'categories' => fn () => $ledger->categories()->orderBy('position')->get(),
            'payees' => fn () => $ledger->payees()->orderBy('name')->get(),
            'bills' => Inertia::defer(function () use ($ledger) {
                $bills = $ledger->bills()
                    ->with(['account', 'category', 'payee', 'transactions' => fn ($q) => $q->with('account')->latest('transaction_date')->limit(5)])
                    ->oldest('next_due_date')
                    ->get();

                $bills->each(function (Bill $bill): void {
                    $bill->missed_cycles = $this->billService->computeMissedCycles($bill);
                });

                return BillResource::collection($bills)->resolve();
            }),
        ]);
    }

    public function create(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/bills/create', [
            'accounts' => fn () => $ledger->accounts()->visible()->orderBy('name')->get(['id', 'ledger_id', 'name', 'color']),
            'categories' => fn () => $ledger->categories()->orderBy('position')->get(),
            'payees' => fn () => $ledger->payees()->orderBy('name')->get(),
        ]);
    }

    public function edit(Request $request, Ledger $ledger, Bill $bill): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/bills/edit', [
            'bill' => new BillResource($bill->load(['account', 'category', 'payee'])),
            'accounts' => fn () => $ledger->accounts()->visible()->orderBy('name')->get(['id', 'ledger_id', 'name', 'color']),
            'categories' => fn () => $ledger->categories()->orderBy('position')->get(),
            'payees' => fn () => $ledger->payees()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreBillRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('view', $ledger);

        $this->billService->store($ledger, $request->validated());

        return redirect()->route('ledgers.bills.index', $ledger)->with('success', 'Recurring transaction created.');
    }

    public function update(UpdateBillRequest $request, Ledger $ledger, Bill $bill): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $this->billService->update($bill, $request->validated());

        return redirect()->route('ledgers.bills.index', $ledger)->with('success', 'Recurring transaction updated.');
    }

    public function destroy(Ledger $ledger, Bill $bill): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $bill->delete();

        return back()->with('success', 'Recurring transaction deleted.');
    }

    public function toggle(Ledger $ledger, Bill $bill): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $bill->update(['is_active' => ! $bill->is_active]);

        return back()->with('success', $bill->is_active ? 'Recurring transaction activated.' : 'Recurring transaction deactivated.');
    }

    public function pay(PayBillRequest $request, Ledger $ledger, Bill $bill): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $this->billService->payBill($bill, $request->validated());

        return back()->with('success', "{$bill->name} marked as paid.");
    }
}
