<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderRequest;
use App\Http\Requests\StoreAccountTypeRequest;
use App\Http\Requests\UpdateAccountTypeRequest;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\AccountType;
use App\Models\Ledger;
use App\Services\SampleDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(private readonly SampleDataService $sampleDataService) {}

    public function index(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        $ledger->load(['accountTypes' => fn ($query) => $query->orderBy('position')]);

        return Inertia::render('ledgers/settings/index', [
            'ledger' => [
                'id' => $ledger->id,
                'name' => $ledger->name,
                'currency_code' => $ledger->currency_code,
                'cycle_start_day' => $ledger->cycle_start_day,
                'uses_seeded_categories' => $ledger->uses_seeded_categories,
            ],
            'accountTypes' => fn () => $ledger->accountTypes,
            'hasSampleData' => fn () => $this->sampleDataService->hasSampleData($ledger),
        ]);
    }

    public function update(UpdateSettingsRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $ledger->update($request->validated());

        return to_route('ledgers.settings.index', $ledger)->with('success', 'Settings saved.');
    }

    public function storeAccountType(StoreAccountTypeRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('view', $ledger);

        $ledger->accountTypes()->create([
            ...$request->validated(),
            'position' => $ledger->accountTypes()->count() + 1,
        ]);

        return to_route('ledgers.settings.index', $ledger)->with('success', 'Account type added.');
    }

    public function updateAccountType(UpdateAccountTypeRequest $request, Ledger $ledger, AccountType $accountType): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $validated = $request->validated();

        $accountType->update($validated);

        return to_route('ledgers.settings.index', $ledger)->with('success', 'Account type updated.');
    }

    public function destroyAccountType(Ledger $ledger, AccountType $accountType): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        if ($accountType->accounts()->exists()) {
            return to_route('ledgers.settings.index', $ledger)
                ->withErrors(['account_type' => 'Cannot delete account type that has accounts.']);
        }

        $accountType->delete();

        return to_route('ledgers.settings.index', $ledger)->with('success', 'Account type deleted.');
    }

    public function reorderAccountTypes(ReorderRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('update', $ledger);

        foreach ($request->items as $item) {
            $ledger->accountTypes()->where('id', $item['id'])->update(['position' => $item['position']]);
        }

        return to_route('ledgers.settings.index', $ledger);
    }
}
