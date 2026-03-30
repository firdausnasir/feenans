<?php

namespace App\Http\Requests;

use App\Models\Ledger;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class PayBillRequest extends LedgerAuthorizationRequest
{
    /**
     * Determine the ledger policy ability required by this request.
     */
    protected function ledgerAbility(): string
    {
        return 'update';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Ledger $ledger */
        $ledger = $this->route('ledger');

        return [
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('ledger_id', $ledger->id)],
            'to_account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('ledger_id', $ledger->id), 'different:account_id'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('ledger_id', $ledger->id)],
            'payee_id' => ['nullable', 'integer', Rule::exists('payees', 'id')->where('ledger_id', $ledger->id)],
            'date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.min' => 'The amount must be at least 0.01.',
            'to_account_id.different' => 'The destination account must be different from the source account.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'account_id' => 'account',
            'to_account_id' => 'destination account',
            'category_id' => 'category',
            'payee_id' => 'payee',
        ];
    }
}
