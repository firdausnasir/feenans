<?php

namespace App\Data\Transactions\Input;

use App\Data\Shared\Input\BaseInputData;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\LaravelData\Attributes\FromAuthenticatedUser;
use Spatie\LaravelData\Attributes\FromRouteParameter;

class GetTransactionIndexData extends BaseInputData
{
    public function __construct(
        #[FromRouteParameter('ledger')] public Ledger $ledger,
        #[FromAuthenticatedUser] public User $user,
        public readonly ?string $search = null,
        public readonly ?string $date_from = null,
        public readonly ?string $date_to = null,
        public readonly mixed $account_ids = null,
        public readonly mixed $category_ids = null,
        public readonly mixed $transaction_types = null,
        public readonly mixed $payee_ids = null,
        public readonly mixed $tag_ids = null,
        public readonly ?string $bill_id = null,
        public readonly ?string $uncategorized = null,
        public readonly int $page = 1,
        public readonly int $per_page = 25,
    ) {}

    public static function authorize(Request $request): bool
    {
        $user = $request->user();
        $ledger = $request->route('ledger');

        return $user !== null
            && $ledger instanceof Ledger
            && $user->can('view', $ledger);
    }

    /**
     * @return array<string, mixed>
     */
    public function toFilterInput(): array
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
