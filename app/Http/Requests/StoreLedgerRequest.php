<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLedgerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
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
            'currency_code' => ['required', 'string', 'size:3', Rule::in($this->validCurrencyCodes())],
            'uses_seeded_categories' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'currency_code.required' => 'Please select a currency.',
            'currency_code.size' => 'Please select a valid currency code.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'currency_code' => 'currency',
            'uses_seeded_categories' => 'default categories',
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
