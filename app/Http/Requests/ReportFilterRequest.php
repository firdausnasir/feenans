<?php

namespace App\Http\Requests;

use App\Models\Ledger;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportFilterRequest extends FormRequest
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
        /** @var Ledger $ledger */
        $ledger = $this->route('ledger');

        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'gte:date_from'],
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('ledger_id', $ledger->id)],
            'compare_start' => ['nullable', 'date_format:Y-m-d', 'required_with:compare_end'],
            'compare_end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:compare_start', 'required_with:compare_start'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_from.date_format' => 'Please enter a valid start date (YYYY-MM-DD).',
            'date_to.date_format' => 'Please enter a valid end date (YYYY-MM-DD).',
            'date_to.gte' => 'The end date must be on or after the start date.',
            'compare_start.date_format' => 'Please enter a valid comparison start date (YYYY-MM-DD).',
            'compare_end.date_format' => 'Please enter a valid comparison end date (YYYY-MM-DD).',
            'compare_end.gte' => 'The comparison end date must be on or after the comparison start date.',
            'compare_start.required_with' => 'A comparison start date is required when providing a comparison end date.',
            'compare_end.required_with' => 'A comparison end date is required when providing a comparison start date.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'date_from' => 'start date',
            'date_to' => 'end date',
            'account_id' => 'account',
            'compare_start' => 'comparison start date',
            'compare_end' => 'comparison end date',
        ];
    }
}
