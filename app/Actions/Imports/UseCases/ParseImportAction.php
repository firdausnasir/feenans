<?php

namespace App\Actions\Imports\UseCases;

use App\Actions\Imports\Queries\DetectImportBankFormatQuery;
use App\Actions\Imports\Queries\ParseImportPreviewQuery;
use App\Data\Imports\Input\ParseImportData;
use App\Data\Imports\Output\Web\ImportParseResultData;

class ParseImportAction
{
    public function __construct(
        private readonly ParseImportPreviewQuery $parseImportPreview,
        private readonly CreatePendingImportHandleAction $createPendingImportHandle,
        private readonly DetectImportBankFormatQuery $detectImportBankFormat,
    ) {}

    public function __invoke(ParseImportData $data): ImportParseResultData
    {
        $preview = ($this->parseImportPreview)($data->file);
        $pendingHandle = ($this->createPendingImportHandle)($data);
        $detection = ($this->detectImportBankFormat)($preview['headers']);

        return new ImportParseResultData(
            headers: $preview['headers'],
            preview_rows: $preview['preview_rows'],
            total_rows: $preview['total_rows'],
            file_path: $pendingHandle->file_path,
            detected_bank: $detection['detected_bank'],
            suggested_mapping: $detection['suggested_mapping'],
            pending_import_handle: $pendingHandle->pending_import_handle,
        );
    }
}
