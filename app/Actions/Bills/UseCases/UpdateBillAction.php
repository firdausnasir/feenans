<?php

namespace App\Actions\Bills\UseCases;

use App\Actions\Payees\UseCases\ResolveLedgerPayeeAction;
use App\Data\Bills\Input\UpdateBillData;
use App\Models\Bill;
use Illuminate\Support\Facades\DB;

class UpdateBillAction
{
    public function __construct(private readonly ResolveLedgerPayeeAction $resolveLedgerPayee) {}

    public function __invoke(UpdateBillData $data): Bill
    {
        return DB::transaction(function () use ($data): Bill {
            $attributes = $data->attributesToUpdate();

            if ($data->payee_id === null && trim((string) $data->new_payee_name) !== '') {
                $attributes['payee_id'] = ($this->resolveLedgerPayee)(
                    $data->ledger,
                    null,
                    $data->new_payee_name,
                )?->id;
            }

            $data->bill->update($attributes);

            /** @var Bill $bill */
            $bill = $data->bill->fresh();

            return $bill->loadMissing(['account', 'toAccount', 'category', 'payee']);
        });
    }
}
