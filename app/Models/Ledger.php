<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\LedgerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ledger extends Model
{
    /** @use HasFactory<LedgerFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'currency_code',
        'uses_seeded_categories',
        'cycle_start_day',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'uses_seeded_categories' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accountTypes(): HasMany
    {
        return $this->hasMany(AccountType::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function payees(): HasMany
    {
        return $this->hasMany(Payee::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function importMappings(): HasMany
    {
        return $this->hasMany(ImportMapping::class);
    }

    public function importRecords(): HasMany
    {
        return $this->hasMany(ImportRecord::class);
    }

    /**
     * Compute the [start, end] Carbon dates for the billing cycle containing the reference date.
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    public function cycleBounds(CarbonImmutable $referenceDate): array
    {
        $startDay = $this->cycle_start_day ?? 1;

        if ($referenceDate->day >= $startDay) {
            $start = $referenceDate->setDay($startDay)->startOfDay();
        } else {
            $prevMonth = $referenceDate->subMonthNoOverflow();
            $start = $prevMonth->setDay(min($startDay, $prevMonth->daysInMonth))->startOfDay();
        }

        $end = $start->addMonthNoOverflow()->subDay()->endOfDay();

        return ['start' => $start, 'end' => $end];
    }
}
