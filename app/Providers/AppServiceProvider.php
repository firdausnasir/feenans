<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Observers\AccountObserver;
use App\Observers\BillObserver;
use App\Observers\BudgetObserver;
use App\Observers\CategoryObserver;
use App\Observers\TransactionObserver;
use App\Policies\LedgerPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Ledger::class, LedgerPolicy::class);
        Transaction::observe(TransactionObserver::class);
        Account::observe(AccountObserver::class);
        Bill::observe(BillObserver::class);
        Category::observe(CategoryObserver::class);
        Budget::observe(BudgetObserver::class);

        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        $this->configureRateLimiting();
        $this->configureDefaults();
    }

    /**
     * Configure API rate limiting.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            // SPA requests use session auth (same-origin with cookie) — no rate limit
            if ($request->hasSession() && $request->session()->has('_token')) {
                return Limit::none();
            }

            // External API token requests get standard rate limit
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
