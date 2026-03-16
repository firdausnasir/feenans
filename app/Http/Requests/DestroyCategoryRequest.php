<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Ledger;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DestroyCategoryRequest extends FormRequest
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

        /** @var Category $category */
        $category = $this->route('category');

        $reassignRule = $ledger instanceof Ledger
            ? Rule::exists('categories', 'id')
                ->where('ledger_id', $ledger->id)
                ->whereNot('id', $category->id)
            : 'exists:categories,id';

        return [
            'reassign_category_id' => ['nullable', 'integer', $reassignRule],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reassign_category_id' => 'reassignment category',
        ];
    }
}
