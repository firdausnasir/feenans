<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class AdjustBalanceRequest extends LedgerAuthorizationRequest
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
        return [
            'amount' => ['required', 'numeric', 'not_in:0'],
            'date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Please provide an adjustment amount.',
            'amount.not_in' => 'The adjustment amount cannot be zero.',
        ];
    }
}
