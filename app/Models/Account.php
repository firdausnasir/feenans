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

    public function getCurrentBalanceAttribute(): string
    {
        $balance = (float) $this->initial_balance + (float) $this->transactions()->sum('amount');

        return number_format($balance, 2, '.', '');
    }
}
