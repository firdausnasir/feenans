<?php

namespace App\Http\Requests;

use App\Enums\ApiTokenAbility;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

class StoreTransactionWebhookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        if (! $user->currentAccessToken() instanceof PersonalAccessToken) {
            return false;
        }

        /** @var PersonalAccessToken $token */
        $token = $user->currentAccessToken();

        return $token->can(ApiTokenAbility::TransactionWebhook->value)
            || ApiTokenAbility::ledgerIdFromWebhookAbilities($token->abilities ?? []) !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'string', 'max:64'],
            'type' => ['nullable', 'string', Rule::in(['income', 'expense'])],
            'date' => ['nullable', 'string', 'max:255'],
            'ledger_id' => ['nullable', 'integer'],
            'account' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
        ];
    }
}
