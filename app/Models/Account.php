<?php

namespace App\Models;

use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'ledger_id',
        'account_type_id',
        'name',
        'color',
        'initial_balance',
        'statement_day',
        'payment_due_day',
        'include_in_totals',
        'is_hidden',
        'position',
        'is_sample',
    ];

    /**
     * The appended accessors.
     *
     * @var list<string>
     */
    protected $appends = [
        'current_balance',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'initial_balance' => 'decimal:2',
            'include_in_totals' => 'boolean',
            'is_hidden' => 'boolean',
            'is_sample' => 'boolean',
        ];
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    public function accountType(): BelongsTo
    {
        return $this->belongsTo(AccountType::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_hidden', false);
    }

    public function scopeHidden(Builder $query): Builder
    {
        return $query->where('is_hidden', true);
    }

    public function scopeWithCurrentBalance(Builder $query): Builder
    {
        return $query->withSum('transactions', 'amount');
    }

    public function currentBalanceAmount(): float
    {
        $attributes = $this->getAttributes();

        return (float) ($attributes['initial_balance'] ?? 0) + $this->transactionsTotalAmount();
    }

    public function transactionsTotalAmount(): float
    {
        $attributes = $this->getAttributes();

        if (array_key_exists('transactions_sum_amount', $attributes)) {
            return (float) ($attributes['transactions_sum_amount'] ?? 0);
        }

        return (float) $this->transactions()->sum('amount');
    }

    public function getCurrentBalanceAttribute(): string
    {
        return number_format($this->currentBalanceAmount(), 2, '.', '');
    }
}
