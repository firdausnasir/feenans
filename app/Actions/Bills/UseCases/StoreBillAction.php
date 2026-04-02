<?php

namespace App\Actions\Bills\UseCases;

use App\Data\Bills\Input\StoreBillData;
use App\Models\Bill;
use App\Models\Ledger;
use Illuminate\Support\Facades\DB;

class StoreBillAction
{
    public function __invoke(StoreBillData $data): Bill
    {
        return DB::transaction(function () use ($data): Bill {
            $bill = $data->ledger->bills()->create([
                'account_id' => $data->account_id,
                'to_account_id' => $data->to_account_id,
                'category_id' => $data->category_id,
                'payee_id' => $this->resolvePayeeId($data->ledger, $data->payee_id, $data->new_payee_name),
                'name' => $data->name,
                'transaction_type' => $data->transaction_type,
                'amount' => $data->amount,
                'recurrence_type' => $data->recurrence_type,
                'recurrence_interval' => $data->recurrence_interval,
                'recurrence_day' => $data->recurrence_day,
                'next_due_date' => $data->next_due_date,
                'auto_create' => $data->auto_create,
                'end_type' => $data->end_type,
                'end_date' => $data->end_date,
                'end_after_occurrences' => $data->end_after_occurrences,
            ]);

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
