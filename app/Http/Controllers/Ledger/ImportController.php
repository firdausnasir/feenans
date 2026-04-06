<?php

namespace App\Http\Controllers\Ledger;

use App\Actions\Imports\Queries\GetImportPageQuery;
use App\Actions\Imports\UseCases\DeleteImportMappingAction;
use App\Actions\Imports\UseCases\ExecuteImportAction;
use App\Actions\Imports\UseCases\ParseImportAction;
use App\Actions\Imports\UseCases\StoreImportMappingAction;
use App\Data\Imports\Input\GetImportPageData;
use App\Data\Imports\Input\ParseImportData;
use App\Data\Imports\Input\StoreImportData;
use App\Data\Imports\Input\StoreImportMappingData;
use App\Http\Controllers\Controller;
use App\Models\ImportMapping;
use App\Models\Ledger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function create(
        Ledger $ledger,
        GetImportPageData $input,
        GetImportPageQuery $getImportPage,
    ): Response {
        $page = $getImportPage($input->ledger, $input);

        return Inertia::render('ledgers/import/index', [
            'parseResult' => fn () => $page->parseResult?->toArray(),
        ]);
    }

    protected function ledgerDisk(): string
    {
        return (string) config('filesystems.ledger_disk', config('filesystems.default', 'local'));
    }

    public function parse(
        Ledger $ledger,
        ParseImportData $data,
        ParseImportAction $parseImport,
    ): RedirectResponse {
        $result = $parseImport($data);

        request()->session()->put($this->pendingImportFilePathSessionKey($ledger), $result->file_path);

        return to_route('ledgers.import.create', $ledger)->with('importParseResult', $result->toArray());
    }

    public function execute(
        Ledger $ledger,
        StoreImportData $data,
        ExecuteImportAction $executeImport,
    ): RedirectResponse {
        $result = $executeImport($data);

        request()->session()->forget($this->pendingImportFilePathSessionKey($ledger));

        return to_route('ledgers.import.create', $ledger)->with('success', $result->message);
    }

    private function pendingImportFilePathSessionKey(Ledger $ledger): string
    {
        return "ledger-imports.{$ledger->id}.file_path";
    }

    public function storeMapping(
        Ledger $ledger,
        StoreImportMappingData $data,
        StoreImportMappingAction $storeImportMapping,
    ): RedirectResponse {
        $storeImportMapping($data);

        return to_route('ledgers.import.create', $ledger)->with('success', 'Mapping saved.');
    }

    public function destroyMapping(
        Ledger $ledger,
        ImportMapping $importMapping,
        DeleteImportMappingAction $deleteImportMapping,
    ): RedirectResponse {
        $this->authorize('view', $ledger);
        $deleteImportMapping($ledger, $importMapping);

        return to_route('ledgers.import.create', $ledger)->with('success', 'Mapping deleted.');
    }
}
