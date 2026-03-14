<?php

namespace App\Http\Requests;

use App\Models\Ledger;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTransactionRequest extends FormRequest
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
        $tagRule = $ledger instanceof Ledger
            ? Rule::exists('tags', 'id')->where('ledger_id', $ledger->id)
            : 'exists:tags,id';

        return [
            'account_id' => ['required', 'integer', $accountRule],
            'to_account_id' => [
                'nullable',
                'integer',
                $accountRule,
                'different:account_id',
                Rule::requiredIf($this->input('transaction_type') === 'transfer'),
            ],
            'category_id' => ['nullable', 'integer', $categoryRule],
            'payee_id' => ['nullable', 'integer', $payeeRule],
            'transaction_type' => ['required', 'string', Rule::in(['expense', 'income', 'transfer'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'transaction_date' => ['required', 'date'],
            'splits' => ['nullable', 'array', 'min:2'],
            'splits.*.amount' => ['required', 'numeric', 'gt:0'],
            'splits.*.category_id' => ['nullable', 'integer', $categoryRule],
            'splits.*.description' => ['nullable', 'string', 'max:255'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', $tagRule],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_id.required' => 'Please select an account.',
            'to_account_id.required' => 'Please select a destination account for this transfer.',
            'to_account_id.different' => 'The destination account must be different from the source account.',
            'transaction_type.required' => 'Please select a transaction type.',
            'transaction_type.in' => 'Please select a valid transaction type (expense, income, or transfer).',
            'amount.required' => 'Please enter an amount.',
            'amount.numeric' => 'Please enter a valid amount.',
            'amount.gt' => 'Please enter an amount greater than zero.',
            'transaction_date.required' => 'Please select a date.',
            'transaction_date.date' => 'Please enter a valid date.',
            'splits.min' => 'A split transaction must have at least two splits.',
            'splits.*.amount.required' => 'Please enter an amount for each split.',
            'splits.*.amount.gt' => 'Please enter an amount greater than zero.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'account_id' => 'account',
            'to_account_id' => 'destination account',
            'category_id' => 'category',
            'payee_id' => 'payee',
            'transaction_type' => 'transaction type',
            'transaction_date' => 'date',
            'tag_ids' => 'tags',
            'splits.*.amount' => 'split amount',
            'splits.*.category_id' => 'split category',
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $splits = $this->input('splits');

                if (! is_array($splits) || $splits === []) {
                    return;
                }

                $total = array_reduce($splits, function (float $carry, mixed $split): float {
                    if (! is_array($split)) {
                        return $carry;
                    }

                    return $carry + (float) ($split['amount'] ?? 0);
                }, 0.0);

                if (round($total, 2) !== round((float) $this->input('amount'), 2)) {
                    $validator->errors()->add('splits', 'Split amounts must equal the transaction total.');
                }
            },
        ];
    }
}
