<?php

namespace App\Http\Requests;

use App\Models\Ledger;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'account_type_id' => ['required', 'integer', $accountTypeRule],
            'initial_balance' => ['required', 'numeric'],
            'statement_day' => ['nullable', 'integer', 'between:1,31'],
            'include_in_totals' => ['required', 'boolean'],
        ];
    }
}
