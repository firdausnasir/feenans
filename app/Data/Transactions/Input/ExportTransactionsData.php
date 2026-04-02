<?php

namespace App\Data\Transactions\Input;

use Illuminate\Http\Request;
use Spatie\LaravelData\Normalizers\Normalizer;

class ExportTransactionsData extends GetTransactionIndexData
{
    public static function normalizers(): array
    {
        return [ExportTransactionsRequestNormalizer::class, ...parent::normalizers()];
    }
}

class ExportTransactionsRequestNormalizer implements Normalizer
{
    public function normalize(mixed $value): ?array
    {
        if (! $value instanceof Request) {
            return null;
        }

        return $value->all();
    }
}
