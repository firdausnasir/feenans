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
}
