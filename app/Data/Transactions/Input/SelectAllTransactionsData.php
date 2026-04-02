<?php

namespace App\Data\Transactions\Input;

use Illuminate\Http\Request;
use Spatie\LaravelData\Normalizers\Normalizer;

class SelectAllTransactionsData extends GetTransactionIndexData
{
    public static function normalizers(): array
    {
        return [SelectAllTransactionsRequestNormalizer::class, ...parent::normalizers()];
    }
}

class SelectAllTransactionsRequestNormalizer implements Normalizer
{
    public function normalize(mixed $value): ?array
    {
        if (! $value instanceof Request) {
            return null;
        }

        return $value->all();
    }
}
