<?php

namespace App\Notifications;

use App\Models\Bill;
use Illuminate\Support\Number;

trait FormatsBillCurrency
{
    private function formatBillAmount(Bill $bill): string
    {
        return Number::currency((float) $bill->amount, in: $bill->ledger?->currency_code ?? Number::defaultCurrency());
    }
}
