<?php

namespace App\Http\Requests;

use App\Models\Ledger;
use Illuminate\Foundation\Http\FormRequest;

abstract class LedgerAuthorizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->authorizeLedger($this->ledgerAbility());
    }

    protected function ledgerAbility(): string
    {
        return 'view';
    }

    protected function authorizeLedger(string $ability): bool
    {
        $user = $this->user();
        $ledger = $this->route('ledger');

        return $user !== null
            && $ledger instanceof Ledger
            && $user->can($ability, $ledger);
    }
}
