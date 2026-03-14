<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveOnboardingStepRequest;
use App\Models\AccountType;
use App\Services\LedgerSetupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function __construct(private LedgerSetupService $ledgerSetupService) {}

    /**
     * Show the onboarding page for the current step.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $ledger = $user->ledgers()->latest()->first();

        if ($user->onboarding_step === null) {
            return $ledger
                ? redirect()->route('ledgers.dashboard', $ledger)
                : redirect()->route('dashboard');
        }

        $account = $ledger?->accounts()->latest()->first();

        return Inertia::render('onboarding/index', [
            'step' => $user->onboarding_step ?? 1,
            'savedData' => $user->onboarding_data,
            'accountTypes' => AccountType::all(),
            'ledger' => $ledger,
            'account' => $account,
        ]);
    }

    /**
     * Save a specific onboarding step and advance to the next.
     */
    public function saveStep(SaveOnboardingStepRequest $request, int $step): RedirectResponse
    {
        $user = $request->user();

        if ($step === 1) {
            $data = $request->validated();

            $this->ledgerSetupService->createForUser($user, [
                'name' => $data['name'],
                'currency_code' => 'MYR',
                'uses_seeded_categories' => (bool) ($data['seed_categories'] ?? false),
                'cycle_start_day' => $data['cycle_start_day'],
            ]);

            $user->update([
                'onboarding_step' => 2,
                'onboarding_data' => null,
            ]);
        } elseif ($step === 2) {
            $data = $request->validated();
            $ledger = $user->ledgers()->latest()->firstOrFail();

            $ledger->accounts()->create([
                'account_type_id' => $data['account_type_id'],
                'name' => $data['name'],
                'initial_balance' => $data['initial_balance'],
                'statement_day' => $data['statement_day'] ?? null,
                'include_in_totals' => (bool) ($data['include_in_totals'] ?? true),
            ]);

            $user->update([
                'onboarding_step' => 3,
                'onboarding_data' => null,
            ]);
        }

        return redirect()->route('onboarding.show');
    }

    /**
     * Autosave partial onboarding form data.
     */
    public function autosave(Request $request): JsonResponse
    {
        $request->user()->update(['onboarding_data' => $request->input('data')]);

        return response()->json(['saved' => true]);
    }

    /**
     * Complete onboarding and redirect to the ledger dashboard.
     */
    public function complete(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_if($user->onboarding_step !== 3, 403);

        $ledger = $user->ledgers()->latest()->firstOrFail();

        $user->update([
            'onboarding_step' => null,
            'onboarding_data' => null,
        ]);

        return redirect()->route('ledgers.dashboard', $ledger);
    }
}
