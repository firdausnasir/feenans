<?php

namespace App\Http\Controllers\Ledger;

use App\Actions\Bills\Queries\GetBillFormPageQuery;
use App\Actions\Bills\Queries\GetBillIndexPageQuery;
use App\Actions\Bills\UseCases\DeleteBillAction;
use App\Actions\Bills\UseCases\PayBillAction;
use App\Actions\Bills\UseCases\StoreBillAction;
use App\Actions\Bills\UseCases\ToggleBillAction;
use App\Actions\Bills\UseCases\UpdateBillAction;
use App\Data\Bills\Input\GetBillFormPageData;
use App\Data\Bills\Input\GetBillIndexPageData;
use App\Data\Bills\Input\PayBillData;
use App\Data\Bills\Input\StoreBillData;
use App\Data\Bills\Input\UpdateBillData;
use App\Data\Bills\Output\Web\BillPageData;
use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Ledger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BillController extends Controller
{
    public function index(
        Ledger $ledger,
        GetBillIndexPageData $input,
        GetBillIndexPageQuery $getBillIndexPage,
    ): Response {
        $resolved = null;
        $resolve = function () use ($input, $getBillIndexPage, &$resolved): BillPageData {
            return $resolved ??= $getBillIndexPage($input->ledger);
        };

        return Inertia::render('ledgers/bills/index', [
            'accounts' => fn () => $resolve()->accounts->map->toArray()->values()->all(),
            'categories' => fn () => $resolve()->categories->map->toArray()->values()->all(),
            'payees' => fn () => $resolve()->payees->map->toArray()->values()->all(),
        ]);
    }

    public function create(
        Ledger $ledger,
        GetBillFormPageData $input,
        GetBillFormPageQuery $getBillFormPage,
    ): Response {
        $resolved = null;
        $resolve = function () use ($input, $getBillFormPage, &$resolved): BillPageData {
            return $resolved ??= $getBillFormPage($input->ledger);
        };

        return Inertia::render('ledgers/bills/create', [
            'accounts' => fn () => $resolve()->accounts->map->toArray()->values()->all(),
            'categories' => fn () => $resolve()->categories->map->toArray()->values()->all(),
            'payees' => fn () => $resolve()->payees->map->toArray()->values()->all(),
        ]);
    }

    public function edit(
        Ledger $ledger,
        Bill $bill,
        GetBillFormPageData $input,
        GetBillFormPageQuery $getBillFormPage,
    ): Response {
        $resolved = null;
        $resolve = function () use ($input, $getBillFormPage, $bill, &$resolved): BillPageData {
            return $resolved ??= $getBillFormPage($input->ledger, $bill);
        };

        return Inertia::render('ledgers/bills/edit', [
            'bill' => fn () => $resolve()->bill?->toArray(),
            'accounts' => fn () => $resolve()->accounts->map->toArray()->values()->all(),
            'categories' => fn () => $resolve()->categories->map->toArray()->values()->all(),
            'payees' => fn () => $resolve()->payees->map->toArray()->values()->all(),
        ]);
    }

    public function store(Ledger $ledger, StoreBillData $data, StoreBillAction $storeBill): RedirectResponse
    {
        $storeBill($data);

        return redirect()->route('ledgers.bills.index', $ledger)->with('success', 'Recurring transaction created.');
    }

    public function update(Ledger $ledger, Bill $bill, UpdateBillData $data, UpdateBillAction $updateBill): RedirectResponse
    {
        $updateBill($data);

        return redirect()->route('ledgers.bills.index', $ledger)->with('success', 'Recurring transaction updated.');
    }

    public function destroy(Ledger $ledger, Bill $bill, DeleteBillAction $deleteBill): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $deleteBill($bill);

        return back()->with('success', 'Recurring transaction deleted.');
    }

    public function toggle(Ledger $ledger, Bill $bill, ToggleBillAction $toggleBill): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $bill = $toggleBill($bill);

        return back()->with('success', $bill->is_active ? 'Recurring transaction activated.' : 'Recurring transaction deactivated.');
    }

    public function pay(Ledger $ledger, Bill $bill, PayBillData $data, PayBillAction $payBill): RedirectResponse
    {
        $payBill($data);

        return back()->with('success', "{$bill->name} marked as paid.");
    }
}
