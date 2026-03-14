<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class ActivityLogService
{
    /**
     * @var list<string>
     */
    protected array $excludedFields = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    public function log(string $action, Model $subject, array $oldValues = [], array $newValues = []): void
    {
        $ledgerId = $subject->getAttribute('ledger_id');
        $userId = Auth::id();

        ActivityLog::query()->create([
            'user_id' => is_numeric($userId) ? (int) $userId : null,
            'ledger_id' => $ledgerId,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
            'created_at' => now(),
        ]);
    }

    protected function sanitize(array $values): array
    {
        return Arr::except($values, $this->excludedFields);
    }
}
