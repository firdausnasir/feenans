<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'onboarding_step',
        'onboarding_data',
        'timezone',
        'privacy_mode',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    protected static function booted(): void
    {
        static::created(function (User $user): void {
            $user->membership()->create([
                'tier' => 'free',
                'status' => 'active',
                'started_at' => now(),
            ]);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'onboarding_data' => 'array',
            'is_admin' => 'boolean',
            'privacy_mode' => 'boolean',
        ];
    }

    public function ledgers(): HasMany
    {
        return $this->hasMany(Ledger::class);
    }

    public function membership(): HasOne
    {
        return $this->hasOne(UserMembership::class);
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    public function isPremium(): bool
    {
        if (! config('app.paywall_enabled')) {
            return true;
        }

        return $this->membership?->tier === 'premium'
            && in_array($this->membership->status, ['active', 'trialing'], true);
    }
}
