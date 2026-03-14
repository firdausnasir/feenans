<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Services\SampleDataService;
use Illuminate\Http\RedirectResponse;

class SampleDataController extends Controller
{
    public function store(Ledger $ledger, SampleDataService $sampleDataService): RedirectResponse
    {
        $this->authorize('update', $ledger);

        if ($sampleDataService->hasSampleData($ledger)) {
            return back()->with('error', 'Sample data already exists.');
        }

        $sampleDataService->generate($ledger);

        return back()->with('success', 'Sample data loaded successfully.');
    }

    public function destroy(Ledger $ledger, SampleDataService $sampleDataService): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $sampleDataService->remove($ledger);

        return back()->with('success', 'Sample data removed successfully.');
    }
}
