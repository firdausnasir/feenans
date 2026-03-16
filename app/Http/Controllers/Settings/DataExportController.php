<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExportController extends Controller
{
    /**
     * Export all ledger data as a JSON file download.
     */
    public function __invoke(Ledger $ledger): StreamedResponse
    {
        $this->authorize('view', $ledger);

        $data = [
            'exported_at' => now()->toIso8601String(),
            'ledger_name' => $ledger->name,
            'currency_code' => $ledger->currency_code,
            'accounts' => $ledger->accounts()->get(),
            'categories' => $ledger->categories()->get(),
            'payees' => $ledger->payees()->get(),
            'tags' => $ledger->tags()->get(),
            'transactions' => $ledger->transactions()->with(['tags', 'splits'])->get(),
            'bills' => $ledger->bills()->get(),
            'budgets' => $ledger->budgets()->get(),
        ];

        $filename = Str::slug($ledger->name).'-export-'.now()->format('Y-m-d').'.json';

        return response()->streamDownload(function () use ($data): void {
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }
}
