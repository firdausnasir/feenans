<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\ParseImportRequest;
use App\Http\Requests\StoreImportRequest;
use App\Models\ImportMapping;
use App\Models\Ledger;
use App\Services\ImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function __construct(private readonly ImportService $importService) {}

    public function create(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/import/index', [
            'parsedImport' => $request->session()->get('import.parse_result'),
            'accounts' => Inertia::defer(fn () => $ledger->accounts()
                ->orderBy('name')
                ->get(['id', 'ledger_id', 'name', 'current_balance', 'color'])
                ->values()
                ->all()),
            'importHistory' => Inertia::defer(fn () => $this->importService->historyForLedger($ledger)),
            'savedMappings' => Inertia::defer(fn () => $this->importService->mappingsForLedger($ledger)),
        ]);
    }

    public function parse(ParseImportRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('view', $ledger);

        try {
            $parseResult = $this->importService->parseUploadedFile($request->file('file'));

            return redirect()->route('ledgers.import.create', $ledger)->with([
                'import.parse_result' => $parseResult,
                'success' => 'CSV parsed successfully.',
            ]);
        } catch (\RuntimeException $exception) {
            return back()->withErrors([
                'file' => $exception->getMessage(),
            ]);
        }
    }

    public function execute(StoreImportRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('view', $ledger);

        try {
            $result = $this->importService->executeImport($ledger, $request->validated());
            $message = "Imported {$result['imported']} transactions";

            if ($result['skipped'] > 0) {
                $message .= ", skipped {$result['skipped']} duplicates";
            }

            return redirect()->route('ledgers.import.create', $ledger)->with('success', $message);
        } catch (\RuntimeException $exception) {
            return back()->withErrors([
                'file_path' => $exception->getMessage(),
            ]);
        }
    }

    public function storeMapping(Request $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('view', $ledger);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mapping' => ['required', 'array'],
        ]);

        $ledger->importMappings()->create([
            'name' => $validated['name'],
            'mapping' => $validated['mapping'],
        ]);

        return back()->with('success', 'Mapping saved.');
    }

    public function destroyMapping(Ledger $ledger, ImportMapping $importMapping): RedirectResponse
    {
        $this->authorize('view', $ledger);

        if ($importMapping->ledger_id !== $ledger->id) {
            abort(404);
        }

        $importMapping->delete();

        return back()->with('success', 'Mapping deleted.');
    }
}
