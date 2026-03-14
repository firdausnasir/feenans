<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Observers\AccountObserver;
use App\Observers\BillObserver;
use App\Observers\TransactionObserver;
use App\Policies\LedgerPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

        $this->configureDefaults();
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
