<?php

namespace App\Http\Requests;

use App\Models\Ledger;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreImportRequest extends FormRequest
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
            'file_path' => ['required', 'string'],
            'account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('ledger_id', $ledger->id)],
            'mapping' => ['required', 'array'],
            'mapping.date' => ['required', 'string'],
            'mapping.amount' => ['required', 'string'],
            'mapping.description' => ['nullable', 'string'],
            'mapping.category' => ['nullable', 'string'],
            'mapping.payee' => ['nullable', 'string'],
            'mapping.type' => ['nullable', 'string'],
            'skip_duplicates' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file_path.required' => 'Please upload a file to import.',
            'account_id.required' => 'Please select an account to import into.',
            'mapping.required' => 'Please configure the column mapping.',
            'mapping.date.required' => 'Please select which column contains the date.',
            'mapping.amount.required' => 'Please select which column contains the amount.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'file_path' => 'file',
            'account_id' => 'account',
            'skip_duplicates' => 'skip duplicates',
            'mapping.date' => 'date column',
            'mapping.amount' => 'amount column',
            'mapping.description' => 'description column',
            'mapping.category' => 'category column',
            'mapping.payee' => 'payee column',
            'mapping.type' => 'type column',
        ];
    }
}
