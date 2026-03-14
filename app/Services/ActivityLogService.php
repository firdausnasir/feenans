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
        'updated_at',
        'created_at',
        'deleted_at',
    ];

    public function log(string $action, Model $subject, array $oldValues = [], array $newValues = []): void
    {
        $ledgerId = $subject->getAttribute('ledger_id');
        $userId = Auth::id();

        $sanitizedOld = $this->sanitize($oldValues);
        $sanitizedNew = $this->sanitize($newValues);

        if ($action === 'updated') {
            [$sanitizedOld, $sanitizedNew] = $this->diffChanges($sanitizedOld, $sanitizedNew);
        }

        ActivityLog::query()->create([
            'user_id' => is_numeric($userId) ? (int) $userId : null,
            'ledger_id' => $ledgerId,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'old_values' => $sanitizedOld,
            'new_values' => $sanitizedNew,
            'created_at' => now(),
        ]);
    }

    /**
     * Filter old/new values to only include fields that actually changed.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    protected function diffChanges(array $oldValues, array $newValues): array
    {
        $changedKeys = [];

        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;

            if ($this->normalizeValue($oldValue) !== $this->normalizeValue($newValue)) {
                $changedKeys[] = $key;
            }
        }

        return [
            Arr::only($oldValues, $changedKeys),
            Arr::only($newValues, $changedKeys),
        ];
    }

    protected function normalizeValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    /**
     * @return array<string, mixed>
     */
    protected function sanitize(array $values): array
    {
        return Arr::except($values, $this->excludedFields);
    }
}
