<?php

namespace App\Actions\Bills\UseCases;

use App\Data\Bills\Input\UpdateBillData;
use App\Models\Bill;
use App\Models\Ledger;
use Illuminate\Support\Facades\DB;

class UpdateBillAction
{
    public function __invoke(UpdateBillData $data): Bill
    {
        return DB::transaction(function () use ($data): Bill {
            $attributes = $data->attributesToUpdate();

            if ($data->payee_id === null && trim((string) $data->new_payee_name) !== '') {
                $attributes['payee_id'] = $this->resolvePayeeId($data->ledger, $data->payee_id, $data->new_payee_name);
            }

            $data->bill->update($attributes);

            /** @var Bill $bill */
            $bill = $data->bill->fresh();

            return $bill->loadMissing(['account', 'toAccount', 'category', 'payee']);
        });
    }

    private function resolvePayeeId(Ledger $ledger, ?int $payeeId, ?string $newPayeeName): ?int
    {
        if ($payeeId !== null) {
            return $payeeId;
        }

        $trimmedName = trim((string) $newPayeeName);

        if ($trimmedName === '') {
            return null;
        }

        return $ledger->payees()->create(['name' => $trimmedName])->id;
    }
}
