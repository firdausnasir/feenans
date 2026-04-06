<?php

namespace App\Actions\Transactions\UseCases;

use App\Models\User;

class CelebrateFirstTransactionAction
{
    public function __invoke(User $user): void
    {
        $onboardingData = $user->onboarding_data ?? [];

        if (! empty($onboardingData['first_transaction_celebrated'])) {
            return;
        }

        $onboardingData['first_transaction_celebrated'] = true;

        $user->update(['onboarding_data' => $onboardingData]);
        session()->flash('first_transaction', true);
    }
}
