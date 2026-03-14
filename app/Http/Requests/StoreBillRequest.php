<?php

namespace App\Http\Requests;

use App\Enums\RecurrenceType;
use App\Enums\TransactionType;
use App\Models\Ledger;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBillRequest extends FormRequest
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

        $accountRule = $ledger instanceof Ledger
            ? Rule::exists('accounts', 'id')->where('ledger_id', $ledger->id)
            : 'exists:accounts,id';
        $categoryRule = $ledger instanceof Ledger
            ? Rule::exists('categories', 'id')->where('ledger_id', $ledger->id)
            : 'exists:categories,id';
        $payeeRule = $ledger instanceof Ledger
            ? Rule::exists('payees', 'id')->where('ledger_id', $ledger->id)
            : 'exists:payees,id';

        return [
            'name' => ['required', 'string', 'max:255'],
            'transaction_type' => ['required', 'string', Rule::in([TransactionType::Expense->value, TransactionType::Income->value])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'account_id' => ['required', 'integer', $accountRule],
            'category_id' => ['nullable', 'integer', $categoryRule],
            'payee_id' => ['nullable', 'integer', $payeeRule],
            'recurrence_type' => ['required', 'string', Rule::in(array_column(RecurrenceType::cases(), 'value'))],
            'recurrence_interval' => ['required', 'integer', 'min:1'],
            'recurrence_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'next_due_date' => ['required', 'date'],
            'auto_create' => ['nullable', 'boolean'],
            'end_type' => ['nullable', 'string', Rule::in(['never', 'on_date', 'after_occurrences'])],
            'end_date' => ['nullable', 'date', 'required_if:end_type,on_date'],
            'end_after_occurrences' => ['nullable', 'integer', 'min:1', 'required_if:end_type,after_occurrences'],
        ];
    }
}
