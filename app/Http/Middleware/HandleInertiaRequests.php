<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Inertia;
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
        $isAdminArea = str_starts_with($request->path(), 'admin');
        $availableLedgers = $user?->ledgers()->orderBy('name')->get(['id', 'name', 'currency_code']) ?? collect();
        $currentLedger = $request->route('ledger');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'isAdminArea' => $isAdminArea,
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar ?? null,
                    'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                    'onboarding_step' => $user->onboarding_step,
                    'is_admin' => (bool) $user->is_admin,
                ] : null,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'import_parse_result' => $request->session()->get('importParseResult'),
                'first_transaction' => $request->session()->get('first_transaction', false),
                'attachment_uploads' => $request->session()->get('attachment_uploads', []),
                'deleted_attachment_id' => $request->session()->get('deleted_attachment_id'),
            ],
            'currentLedger' => $currentLedger ? [
                'id' => $currentLedger->id,
                'name' => $currentLedger->name,
                'currency_code' => $currentLedger->currency_code,
                'cycle_start_day' => $currentLedger->cycle_start_day,
            ] : null,
            'availableLedgers' => $isAdminArea ? [] : $availableLedgers->values(),
            'unread_notifications_count' => $user?->unreadNotifications()->count() ?? 0,
            'notifications' => Inertia::optional(function () use ($user) {
                if ($user === null) {
                    return null;
                }

                $notifications = $user->unreadNotifications()
                    ->latest()
                    ->paginate(10);

                return [
                    'data' => $notifications->items(),
                    'meta' => [
                        'page' => $notifications->currentPage(),
                        'per_page' => $notifications->perPage(),
                        'total' => $notifications->total(),
                    ],
                ];
            }),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'transactionModalData' => $isAdminArea ? null : Inertia::optional(function () use ($currentLedger) {
                if (! $currentLedger) {
                    return null;
                }

                $categories = $currentLedger->categories()
                    ->orderBy('position')
                    ->get();

                // Build parent-child tree, then flatten for the modal
                $parentCategories = $categories->whereNull('parent_id')->values();
                $flatCategories = [];

                foreach ($parentCategories as $parent) {
                    $flatCategories[] = $parent;
                    $children = $categories->where('parent_id', $parent->id)->values();

                    foreach ($children as $child) {
                        $flatCategories[] = $child;
                    }
                }

                return [
                    'accounts' => $currentLedger->accounts()
                        ->visible()
                        ->orderBy('position')
                        ->orderBy('name')
                        ->get(['id', 'ledger_id', 'name', 'current_balance', 'color']),
                    'categories' => $flatCategories,
                    'payees' => $currentLedger->payees()->orderBy('name')->get(),
                    'tags' => $currentLedger->tags()->orderBy('name')->get(),
                ];
            }),
        ];
    }
}
