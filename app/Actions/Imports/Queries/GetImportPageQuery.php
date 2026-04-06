<?php

namespace App\Actions\Imports\Queries;

use App\Data\Accounts\Output\Web\AccountData;
use App\Data\Imports\Input\GetImportPageData;
use App\Data\Imports\Output\Web\ImportHistoryData;
use App\Data\Imports\Output\Web\ImportMappingData;
use App\Data\Imports\Output\Web\ImportPageData;
use App\Data\Imports\Output\Web\ImportParseResultData;
use App\Models\Account;
use App\Models\ImportMapping;
use App\Models\ImportRecord;
use App\Models\Ledger;

class GetImportPageQuery
{
    public function __invoke(Ledger $ledger, GetImportPageData $input): ImportPageData
    {
        return new ImportPageData(
            parseResult: is_array($input->parseResult) ? ImportParseResultData::fromSession($input->parseResult) : null,
            accountsFactory: fn () => $ledger->accounts()
                ->visible()
                ->withCurrentBalance()
                ->orderBy('position')
                ->orderBy('name')
                ->get()
                ->map(fn (Account $account) => AccountData::fromModel($account)->toArray())
                ->values()
                ->all(),
            savedMappingsFactory: fn () => $ledger->importMappings()
                ->orderBy('name')
                ->get()
                ->map(fn (ImportMapping $mapping) => ImportMappingData::fromModel($mapping)->toArray())
                ->values()
                ->all(),
            importHistoryFactory: fn () => $ledger->importRecords()
                ->orderByDesc('imported_at')
                ->limit(50)
                ->get()
                ->map(fn (ImportRecord $record) => ImportHistoryData::fromModel($record)->toArray())
                ->values()
                ->all(),
        );
    }
}
