<?php

namespace App\Data\Transactions\Output\Web;

class TransactionFiltersData
{
    /**
     * @param  string[]  $account_ids
     * @param  string[]  $category_ids
     * @param  string[]  $transaction_types
     * @param  string[]  $payee_ids
     * @param  string[]  $tag_ids
     */
    public function __construct(
        public readonly ?string $search,
        public readonly string $date_from,
        public readonly string $date_to,
        public readonly array $account_ids,
        public readonly array $category_ids,
        public readonly array $transaction_types,
        public readonly array $payee_ids,
        public readonly array $tag_ids,
        public readonly ?string $bill_id,
        public readonly ?string $uncategorized,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
            'account_ids' => $this->account_ids,
            'category_ids' => $this->category_ids,
            'transaction_types' => $this->transaction_types,
            'payee_ids' => $this->payee_ids,
            'tag_ids' => $this->tag_ids,
            'bill_id' => $this->bill_id,
            'uncategorized' => $this->uncategorized,
        ];
    }
}
