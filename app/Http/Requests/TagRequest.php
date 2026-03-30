<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class TagRequest extends LedgerAuthorizationRequest
{
    /**
     * Determine the ledger policy ability required by this request.
     */
    protected function ledgerAbility(): string
    {
        return $this->isMethod('post') ? 'view' : 'update';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please enter a tag name.',
            'color.regex' => 'Please enter a valid hex color like #FF0000.',
        ];
    }
}
