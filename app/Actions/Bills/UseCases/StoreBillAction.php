<?php

namespace App\Actions\Bills\UseCases;

use App\Actions\Payees\UseCases\ResolveLedgerPayeeAction;
use App\Data\Bills\Input\StoreBillData;
use App\Models\Bill;
use Illuminate\Support\Facades\DB;

class StoreBillAction
{
    public function __construct(private readonly ResolveLedgerPayeeAction $resolveLedgerPayee) {}

    public function __invoke(StoreBillData $data): Bill
    {
        return DB::transaction(function () use ($data): Bill {
            $payee = ($this->resolveLedgerPayee)(
                $data->ledger,
                $data->payee_id,
                $data->new_payee_name,
            );

            $bill = $data->ledger->bills()->create([
                'account_id' => $data->account_id,
                'to_account_id' => $data->to_account_id,
                'category_id' => $data->category_id,
                'payee_id' => $payee?->id,
                'name' => $data->name,
                'transaction_type' => $data->transaction_type,
                'amount' => $data->amount,
                'recurrence_type' => $data->recurrence_type,
                'recurrence_interval' => $data->recurrence_interval,
                'recurrence_day' => $data->recurrence_day,
                'next_due_date' => $data->next_due_date,
                'auto_create' => $data->auto_create,
                'is_active' => $data->is_active,
                'notify_email' => $data->notify_email,
                'end_type' => $data->end_type,
                'end_date' => $data->end_date,
                'end_after_occurrences' => $data->end_after_occurrences,
            ]);

            return $bill->loadMissing(['account', 'toAccount', 'category', 'payee']);
        });
    }
}
