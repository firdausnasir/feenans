<?php

namespace App\Http\Requests;

use App\Enums\RecurrenceType;
use App\Enums\TransactionType;
use App\Models\Ledger;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBillRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'min:2', 'max:255'],
            'transaction_type' => ['sometimes', 'string', Rule::in([TransactionType::Expense->value, TransactionType::Income->value])],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'account_id' => ['sometimes', 'integer', $accountRule],
            'category_id' => ['nullable', 'integer', $categoryRule],
            'payee_id' => ['nullable', 'integer', $payeeRule],
            'recurrence_type' => ['sometimes', 'string', Rule::in(array_column(RecurrenceType::cases(), 'value'))],
            'recurrence_interval' => ['sometimes', 'integer', 'min:1'],
            'recurrence_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'next_due_date' => ['sometimes', 'date'],
            'auto_create' => ['nullable', 'boolean'],
            'end_type' => ['nullable', 'string', Rule::in(['never', 'on_date', 'after_occurrences'])],
            'end_date' => ['nullable', 'date', 'required_if:end_type,on_date'],
            'end_after_occurrences' => ['nullable', 'integer', 'min:1', 'required_if:end_type,after_occurrences'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.min' => 'The amount must be at least 0.01.',
            'recurrence_interval.min' => 'The recurrence interval must be at least 1.',
            'next_due_date.date' => 'Please enter a valid due date.',
            'end_date.required_if' => 'Please select an end date.',
            'end_after_occurrences.required_if' => 'Please enter the number of occurrences.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'account_id' => 'account',
            'category_id' => 'category',
            'payee_id' => 'payee',
            'transaction_type' => 'transaction type',
            'recurrence_type' => 'recurrence type',
            'recurrence_interval' => 'recurrence interval',
            'recurrence_day' => 'recurrence day',
            'next_due_date' => 'next due date',
            'auto_create' => 'auto-create',
            'end_type' => 'end type',
            'end_date' => 'end date',
            'end_after_occurrences' => 'number of occurrences',
        ];
    }
}
