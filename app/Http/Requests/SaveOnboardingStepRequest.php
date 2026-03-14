<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveOnboardingStepRequest extends FormRequest
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
        return match ((int) $this->route('step')) {
            1 => [
                'name' => ['required', 'string', 'max:255'],
                'currency_code' => ['nullable', 'string', 'size:3'],
                'cycle_start_day' => ['required', 'integer', 'between:1,31'],
                'seed_categories' => ['nullable', 'boolean'],
            ],
            2 => [
                'name' => ['required', 'string', 'max:255'],
                'account_type_id' => ['required', 'integer', 'exists:account_types,id'],
                'initial_balance' => ['required', 'numeric'],
                'statement_day' => ['nullable', 'integer', 'between:1,31'],
                'include_in_totals' => ['nullable', 'boolean'],
            ],
            // Step 3 completes onboarding with no required fields.
            // Unknown steps also pass through; the route should constrain {step} to 1–3.
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'currency_code.size' => 'Please select a valid currency code.',
            'cycle_start_day.required' => 'Please select a cycle start day.',
            'cycle_start_day.between' => 'The cycle start day must be between 1 and 31.',
            'account_type_id.required' => 'Please select an account type.',
            'initial_balance.required' => 'Please enter an initial balance.',
            'initial_balance.numeric' => 'Please enter a valid initial balance.',
            'statement_day.between' => 'The statement day must be between 1 and 31.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'currency_code' => 'currency',
            'cycle_start_day' => 'cycle start day',
            'seed_categories' => 'default categories',
            'account_type_id' => 'account type',
            'initial_balance' => 'initial balance',
            'statement_day' => 'statement day',
            'include_in_totals' => 'include in totals',
        ];
    }
}
