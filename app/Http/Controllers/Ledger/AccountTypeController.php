<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderRequest;
use App\Http\Requests\StoreAccountTypeRequest;
use App\Models\AccountType;
use App\Models\Ledger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccountTypeController extends Controller
{
    public function store(StoreAccountTypeRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('view', $ledger);

        $ledger->accountTypes()->create([
            ...$request->validated(),
            'position' => $ledger->accountTypes()->count() + 1,
        ]);

        return back();
    }

    public function update(Request $request, Ledger $ledger, AccountType $accountType): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_credit' => ['boolean'],
        ]);

        $accountType->update($validated);

        return back();
    }

    public function destroy(Request $request, Ledger $ledger, AccountType $accountType): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        if ($accountType->accounts()->exists()) {
            return back()->withErrors(['account_type' => 'Cannot delete account type that has accounts.']);
        }

        $accountType->delete();

        return back();
    }

    public function reorder(ReorderRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('update', $ledger);

        foreach ($request->items as $item) {
            $ledger->accountTypes()->where('id', $item['id'])->update(['position' => $item['position']]);
        }

        return back();
    }
}
