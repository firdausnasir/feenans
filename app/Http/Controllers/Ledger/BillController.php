<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayBillRequest;
use App\Http\Requests\StoreBillRequest;
use App\Http\Requests\UpdateBillRequest;
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
            'ledger' => $ledger,
            'bills' => $ledger->bills()
                ->with([
                    'account',
                    'category',
                    'payee',
                    'transactions' => fn ($query) => $query
                        ->with('account')
                        ->latest('transaction_date')
                        ->latest('id')
                        ->limit(10),
                ])
                ->get(),
            'accounts' => $ledger->accounts()->with('accountType')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/bills/create', [
            'ledger' => $ledger,
            'accounts' => $ledger->accounts()->visible()->with('accountType')->orderBy('name')->get(),
            'categories' => $ledger->categories()->with('children')->parents()->orderBy('position')->get(),
            'payees' => $ledger->payees()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreBillRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('view', $ledger);

        $this->billService->store($ledger, $request->validated());

        return to_route('ledgers.bills.index', $ledger);
    }

    public function edit(Request $request, Ledger $ledger, Bill $bill): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/bills/edit', [
            'ledger' => $ledger,
            'bill' => $bill,
            'accounts' => $ledger->accounts()
                ->where(fn ($q) => $q->visible()->orWhere('id', $bill->account_id))
                ->with('accountType')
                ->orderBy('name')
                ->get(),
            'categories' => $ledger->categories()->with('children')->parents()->orderBy('position')->get(),
            'payees' => $ledger->payees()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateBillRequest $request, Ledger $ledger, Bill $bill): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $this->billService->update($bill, $request->validated());

        return to_route('ledgers.bills.index', $ledger);
    }

    public function destroy(Request $request, Ledger $ledger, Bill $bill): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $bill->delete();

        return to_route('ledgers.bills.index', $ledger);
    }

    public function pay(PayBillRequest $request, Ledger $ledger, Bill $bill): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $overrides = $request->validated();

        $this->billService->payBill($bill, $overrides);

        return back();
    }

    public function toggle(Request $request, Ledger $ledger, Bill $bill): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $bill->update(['is_active' => ! $bill->is_active]);

        return back();
    }
}
