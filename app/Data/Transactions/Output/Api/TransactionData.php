<?php

namespace App\Data\Transactions\Output\Api;

use App\Data\Transactions\Output\Web\TransactionData as WebTransactionData;
use App\Models\Transaction;

class TransactionData extends WebTransactionData
{
    public static function fromModel(Transaction $transaction, bool $includeTransferPair = true): self
    {
        return self::from(WebTransactionData::fromModel($transaction, $includeTransferPair)->toArray());
    }
}
