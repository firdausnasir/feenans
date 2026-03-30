<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class BulkDestroyTransactionsRequest extends LedgerAuthorizationRequest
{
    /**
     * Determine the ledger policy ability required by this request.
     */
    protected function ledgerAbility(): string
    {
        return 'delete';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'apply_to_all_matching' => ['sometimes', 'boolean'],
            'ids' => ['required_without:apply_to_all_matching', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
            'excluded_ids' => ['nullable', 'array'],
            'excluded_ids.*' => ['integer'],
            'filters' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required_without' => 'Please select at least one transaction.',
            'ids.min' => 'Please select at least one transaction.',
        ];
    }
}
