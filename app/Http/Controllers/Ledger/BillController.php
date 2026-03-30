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
            'accounts' => fn () => $this->billAccountOptions($ledger),
            'categories' => fn () => $ledger->categories()->orderBy('position')->get(),
            'payees' => fn () => $ledger->payees()->orderBy('name')->get(),
            'bills' => Inertia::defer(function () use ($ledger) {
                $bills = $ledger->bills()
                    ->with(['account', 'toAccount', 'category', 'payee', 'transactions' => fn ($q) => $q->with('account')->latest('transaction_date')->limit(5)])
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
            'accounts' => fn () => $this->billAccountOptions($ledger),
            'categories' => fn () => $ledger->categories()->orderBy('position')->get(),
            'payees' => fn () => $ledger->payees()->orderBy('name')->get(),
        ]);
    }

    public function edit(Request $request, Ledger $ledger, Bill $bill): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/bills/edit', [
            'bill' => new BillResource($bill->load(['account', 'toAccount', 'category', 'payee'])),
            'accounts' => fn () => $this->billAccountOptions($ledger),
            'categories' => fn () => $ledger->categories()->orderBy('position')->get(),
            'payees' => fn () => $ledger->payees()->orderBy('name')->get(),
        ]);
    }

    /**
     * @return array<int, array{id: int, ledger_id: int, name: string, color: ?string}>
     */
    private function billAccountOptions(Ledger $ledger): array
    {
        return $ledger->accounts()
            ->visible()
            ->orderBy('name')
            ->get(['id', 'ledger_id', 'name', 'color'])
            ->map(fn ($account) => [
                'id' => $account->id,
                'ledger_id' => $account->ledger_id,
                'name' => $account->name,
                'color' => $account->color,
            ])
            ->all();
    }

    public function store(StoreBillRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('view', $ledger);

        $validated = $this->resolveInlinePayee($request->validated(), $ledger);

        $this->billService->store($ledger, $validated);

        return redirect()->route('ledgers.bills.index', $ledger)->with('success', 'Recurring transaction created.');
    }

    public function update(UpdateBillRequest $request, Ledger $ledger, Bill $bill): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $validated = $this->resolveInlinePayee($request->validated(), $ledger);

        $this->billService->update($bill, $validated);

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

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function resolveInlinePayee(array $validated, Ledger $ledger): array
    {
        $newPayeeName = trim((string) ($validated['new_payee_name'] ?? ''));

        if ($newPayeeName === '' || ! empty($validated['payee_id'])) {
            return $validated;
        }

        $payee = $ledger->payees()->create(['name' => $newPayeeName]);
        $validated['payee_id'] = $payee->id;

        return $validated;
    }

    public function pay(PayBillRequest $request, Ledger $ledger, Bill $bill): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $this->billService->payBill($bill, $request->validated());

        return back()->with('success', "{$bill->name} marked as paid.");
    }
}
