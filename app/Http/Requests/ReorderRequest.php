<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class ReorderRequest extends LedgerAuthorizationRequest
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
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.position' => ['required', 'integer', 'min:0'],
        ];
    }
}
