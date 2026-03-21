<?php

namespace App\Models;

use App\Enums\RecurrenceType;
use App\Enums\TransactionType;
use Carbon\CarbonImmutable;
use Database\Factories\BillFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bill extends Model
{
    /** @use HasFactory<BillFactory> */
    use HasFactory;

    const END_TYPE_ON_DATE = 'on_date';

    const END_TYPE_AFTER_OCCURRENCES = 'after_occurrences';

    const END_TYPE_NEVER = 'never';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'ledger_id',
        'account_id',
        'to_account_id',
        'category_id',
        'payee_id',
        'name',
        'transaction_type',
        'amount',
        'recurrence_type',
        'recurrence_interval',
        'recurrence_day',
        'next_due_date',
        'auto_create',
        'end_type',
        'end_after_occurrences',
        'end_date',
        'occurrences_count',
        'is_active',
        'is_sample',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'next_due_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'auto_create' => 'boolean',
            'is_active' => 'boolean',
            'is_sample' => 'boolean',
            'recurrence_type' => RecurrenceType::class,
            'transaction_type' => TransactionType::class,
        ];
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function payee(): BelongsTo
    {
        return $this->belongsTo(Payee::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('next_due_date', '>', CarbonImmutable::today())
            ->where('next_due_date', '<=', CarbonImmutable::today()->addDays(7));
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->where('next_due_date', CarbonImmutable::today());
    }

    public function scopeMissed(Builder $query): Builder
    {
        return $query->where('next_due_date', '<', CarbonImmutable::today())
            ->where('auto_create', false);
    }

    public function nextDueDateAfter(CarbonImmutable $from): CarbonImmutable
    {
        $interval = $this->recurrence_interval ?? 1;

        $next = match ($this->recurrence_type) {
            RecurrenceType::Monthly, RecurrenceType::Custom => $from->addMonths($interval),
            RecurrenceType::Weekly => $from->addWeeks($interval),
            RecurrenceType::Daily => $from->addDays($interval),
            RecurrenceType::Yearly => $from->addYears($interval),
        };

        if (
            in_array($this->recurrence_type, [RecurrenceType::Monthly, RecurrenceType::Custom], true)
            && $this->recurrence_day !== null
        ) {
            $next = $next->setDay(min($this->recurrence_day, $next->daysInMonth));
        }

        return $next;
    }

    public function hasReachedEnd(): bool
    {
        if ($this->end_type === self::END_TYPE_ON_DATE && $this->end_date !== null) {
            return CarbonImmutable::today()->gte($this->end_date);
        }

        if ($this->end_type === self::END_TYPE_AFTER_OCCURRENCES && $this->end_after_occurrences !== null) {
            return $this->occurrences_count >= $this->end_after_occurrences;
        }

        return false;
    }
}
