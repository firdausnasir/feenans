<?php

namespace App\Actions\Payees\UseCases;

use App\Models\Ledger;
use App\Models\Payee;

class ResolveLedgerPayeeAction
{
    public function __invoke(Ledger $ledger, ?int $payeeId = null, ?string $newPayeeName = null): ?Payee
    {
        if ($payeeId !== null) {
            return $ledger->payees()->findOrFail($payeeId);
        }

        $trimmedName = trim((string) $newPayeeName);

        if ($trimmedName === '') {
            return null;
        }

        /** @var Payee $payee */
        $payee = $ledger->payees()->firstOrCreate([
            'name' => $trimmedName,
        ]);

        return $payee;
    }
}
