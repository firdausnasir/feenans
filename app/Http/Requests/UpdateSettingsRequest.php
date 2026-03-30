<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends LedgerAuthorizationRequest
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
            'name' => ['required', 'string', 'max:255'],
            'cycle_start_day' => ['required', 'integer', 'between:1,31'],
            'currency_code' => ['sometimes', 'string', 'size:3', Rule::in($this->validCurrencyCodes())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cycle_start_day.required' => 'Please select a cycle start day.',
            'cycle_start_day.between' => 'The cycle start day must be between 1 and 31.',
            'currency_code.size' => 'Please select a valid currency code.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cycle_start_day' => 'cycle start day',
            'currency_code' => 'currency',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function validCurrencyCodes(): array
    {
        return [
            'MYR', 'USD', 'EUR', 'GBP', 'SGD', 'JPY', 'CNY', 'KRW', 'THB', 'IDR',
            'PHP', 'VND', 'INR', 'AUD', 'NZD', 'CAD', 'CHF', 'HKD', 'TWD', 'AED',
            'SAR', 'BRL', 'MXN', 'ZAR', 'SEK', 'NOK', 'DKK', 'PLN', 'TRY', 'RUB',
            'BDT', 'PKR', 'LKR', 'MMK', 'KHR', 'LAK', 'BND', 'NGN', 'EGP', 'KES',
        ];
    }
}
