<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $availableLedgers = $user?->ledgers()->orderBy('name')->get(['id', 'name', 'currency_code']) ?? collect();
        $currentLedger = $request->route('ledger');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'onboarding_step' => $user->onboarding_step,
                ] : null,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'currentLedger' => $currentLedger ? [
                'id' => $currentLedger->id,
                'name' => $currentLedger->name,
                'currency_code' => $currentLedger->currency_code,
                'cycle_start_day' => $currentLedger->cycle_start_day,
            ] : null,
            'availableLedgers' => $availableLedgers->values(),
            'unread_notifications_count' => $user?->unreadNotifications()->count() ?? 0,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
