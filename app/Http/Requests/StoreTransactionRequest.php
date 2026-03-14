<?php

namespace App\Http\Requests;

use App\Models\Ledger;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTransactionRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'not_in:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'transaction_date' => ['required', 'date'],
            'splits' => ['nullable', 'array', 'min:2'],
            'splits.*.amount' => ['required', 'numeric', 'not_in:0'],
            'splits.*.category_id' => ['nullable', 'integer', $categoryRule],
            'splits.*.description' => ['nullable', 'string', 'max:255'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', $tagRule],
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
