<?php

namespace App\Data\Transactions\Input;

class TransactionWebhookPayloadData
{
    /**
     * @param  positive-int  $user_id
     * @param  positive-int  $ledger_id
     * @param  positive-int  $account_id
     */
    public function __construct(
        public int $user_id,
        public int $ledger_id,
        public int $account_id,
        public string $account_name,
        public string $transaction_type,
        public float $amount,
        public string $transaction_date,
        public string $description,
    ) {}

    /**
     * @return array{
     *     user_id:int,
     *     ledger_id:int,
     *     account_id:int,
     *     account_name:string,
     *     transaction_type:string,
     *     amount:float,
     *     transaction_date:string,
     *     description:string
     * }
     */
    public function toQueuePayload(): array
    {
        return [
            'user_id' => $this->user_id,
            'ledger_id' => $this->ledger_id,
            'account_id' => $this->account_id,
            'account_name' => $this->account_name,
            'transaction_type' => $this->transaction_type,
            'amount' => $this->amount,
            'transaction_date' => $this->transaction_date,
            'description' => $this->description,
        ];
    }
}
