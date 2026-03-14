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
}
