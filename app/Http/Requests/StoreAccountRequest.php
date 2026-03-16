<?php

namespace App\Http\Requests;

use App\Models\Ledger;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
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

        $accountTypeRule = $ledger instanceof Ledger
            ? Rule::exists('account_types', 'id')->where('ledger_id', $ledger->id)
            : 'exists:account_types,id';

        return [
            'account_type_id' => ['required', 'integer', $accountTypeRule],
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:7'],
            'initial_balance' => ['required', 'numeric'],
            'statement_day' => ['nullable', 'integer', 'between:1,31'],
            'include_in_totals' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_type_id.required' => 'Please select an account type.',
            'initial_balance.required' => 'Please enter an initial balance.',
            'initial_balance.numeric' => 'Please enter a valid initial balance.',
            'statement_day.between' => 'The statement day must be between 1 and 31.',
            'include_in_totals.required' => 'Please specify whether to include this account in totals.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'account_type_id' => 'account type',
            'initial_balance' => 'initial balance',
            'statement_day' => 'statement day',
            'include_in_totals' => 'include in totals',
        ];
    }
}
