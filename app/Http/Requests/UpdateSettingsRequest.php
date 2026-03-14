<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
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
            'cycle_start_day' => ['required', 'integer', 'between:1,31'],
            'currency_code' => ['sometimes', 'string', 'size:3'],
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
}
