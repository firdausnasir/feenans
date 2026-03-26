# Admin Console Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign the admin console from a standalone single-page layout to a multi-page sidebar layout matching the user experience, remove all page analytics infrastructure, and add comprehensive dashboard metrics.

**Architecture:** The admin console reuses the existing `AppLayout` / `AppSidebar` infrastructure by detecting an `isAdminArea` Inertia shared prop. When true, the sidebar shows admin navigation (Dashboard, Users, Memberships) instead of ledger navigation, and hides ledger-specific UI (LedgerSwitcher, Add Transaction, NotificationBell). Three separate Inertia pages replace the current single-page design. All data is fetched from API endpoints via `fetch()` (Inertia renders only the page shell).

**Tech Stack:** Laravel 12, Inertia v2, React 19, Tailwind CSS v4, Pest 4, Wayfinder

---

## File Map

### Delete

- `app/Http/Middleware/TrackPageAnalytics.php`
- `app/Http/Controllers/Admin/AdminAnalyticsController.php`
- `app/Http/Controllers/Admin/AdminPageController.php` (replaced by `AdminDashboardPageController`)
- `app/Models/DailyPageAnalytics.php`
- `database/migrations/2026_03_26_124054_create_daily_page_analytics_table.php`

### Create

- `app/Http/Controllers/Admin/AdminDashboardPageController.php` — Inertia render for `/admin`
- `app/Http/Controllers/Admin/AdminUserPageController.php` — Inertia render for `/admin/users`
- `app/Http/Controllers/Admin/AdminMembershipPageController.php` — Inertia render for `/admin/memberships`
- `app/Http/Controllers/Admin/AdminMembershipController.php` — API: index + update membership
- `database/migrations/2026_03_26_200000_drop_daily_page_analytics_table.php` — drops the table
- `resources/js/pages/admin/users/index.tsx` — Users page
- `resources/js/pages/admin/memberships/index.tsx` — Memberships page

### Modify

- `bootstrap/app.php:31-38` — Remove `TrackPageAnalytics` from web middleware
- `routes/web.php:24-26` — Add admin users + memberships web routes
- `routes/api.php` — Remove analytics route, add memberships API routes, restructure
- `app/Http/Controllers/Admin/AdminOverviewController.php` — Remove analytics, add ledger/transaction/signup counts
- `app/Http/Controllers/Admin/AdminUserController.php` — Remove `updateMembership` method (moved to AdminMembershipController)
- `app/Http/Middleware/HandleInertiaRequests.php:37-126` — Add `isAdminArea` shared prop
- `resources/js/types/index.ts` — Add `isAdminArea` to shared page props type (via `global.d.ts` or inline type assertion)
- `resources/js/components/app-sidebar.tsx` — Detect admin mode, show admin nav groups
- `resources/js/components/app-sidebar-header.tsx` — Hide Add Transaction + NotificationBell in admin mode
- `resources/js/pages/admin/index.tsx` — Rewrite as dashboard-only page using `AppLayout`
- `tests/Feature/Admin/AdminConsoleTest.php` — Remove analytics tests, update overview assertions, add new route tests

---

## Task 1: Remove Analytics Infrastructure

**Files:**

- Delete: `app/Http/Middleware/TrackPageAnalytics.php`
- Delete: `app/Http/Controllers/Admin/AdminAnalyticsController.php`
- Delete: `app/Models/DailyPageAnalytics.php`
- Delete: `database/migrations/2026_03_26_124054_create_daily_page_analytics_table.php`
- Create: `database/migrations/2026_03_26_200000_drop_daily_page_analytics_table.php`
- Modify: `bootstrap/app.php:31-38`
- Modify: `routes/api.php:10`

- [ ] **Step 1: Create drop migration**

```bash
php artisan make:migration drop_daily_page_analytics_table --no-interaction
```

Migration content:

```php
public function up(): void
{
    Schema::dropIfExists('daily_page_analytics');
}
```

- [ ] **Step 2: Remove TrackPageAnalytics from bootstrap/app.php**

In `bootstrap/app.php`, remove `TrackPageAnalytics::class` from the `$middleware->web(append: [...])` array (line 35) and remove the `use` import (line 8).

- [ ] **Step 3: Remove analytics API route**

In `routes/api.php`, remove line 10:

```php
Route::get('analytics/pages', AdminAnalyticsController::class)->name('admin.analytics.pages');
```

Remove the `use App\Http\Controllers\Admin\AdminAnalyticsController;` import.

- [ ] **Step 4: Delete files**

```bash
rm app/Http/Middleware/TrackPageAnalytics.php
rm app/Http/Controllers/Admin/AdminAnalyticsController.php
rm app/Models/DailyPageAnalytics.php
rm database/migrations/2026_03_26_124054_create_daily_page_analytics_table.php
```

- [ ] **Step 5: Remove analytics from AdminOverviewController**

In `app/Http/Controllers/Admin/AdminOverviewController.php`, remove the `DailyPageAnalytics` import and the `analytics` key from the JSON response (lines 34-40, 51-54).

- [ ] **Step 6: Remove analytics tests from AdminConsoleTest.php**

Delete these two tests:

- `'analytics middleware stores aggregate page hits by route name only'` (lines 159-187)
- `'analytics middleware ignores admin and api requests'` (lines 189-212)

Remove `use App\Models\DailyPageAnalytics;` and `use Illuminate\Support\Carbon;` imports.

Update the `'admin overview returns aggregate counts without ledger data'` test: remove all `DailyPageAnalytics::query()->create(...)` setup and remove assertions for `analytics.today_hits` and `analytics.last_30_days_hits`.

- [ ] **Step 7: Run migrations and tests**

```bash
php artisan migrate --no-interaction
php artisan test --compact tests/Feature/Admin/AdminConsoleTest.php
```

- [ ] **Step 8: Commit**

```bash
git add -A && git commit -m "refactor: remove daily_page_analytics infrastructure"
```

---

## Task 2: Restructure Admin Routes (Web + API)

**Files:**

- Delete: `app/Http/Controllers/Admin/AdminPageController.php`
- Create: `app/Http/Controllers/Admin/AdminDashboardPageController.php`
- Create: `app/Http/Controllers/Admin/AdminUserPageController.php`
- Create: `app/Http/Controllers/Admin/AdminMembershipPageController.php`
- Create: `app/Http/Controllers/Admin/AdminMembershipController.php`
- Modify: `app/Http/Controllers/Admin/AdminUserController.php` — remove `updateMembership`
- Modify: `routes/web.php:24-26`
- Modify: `routes/api.php`

- [ ] **Step 1: Write failing tests for new routes**

In `tests/Feature/Admin/AdminConsoleTest.php`, update existing tests and add new ones:

Update `'non-admin users cannot access the admin console or admin api'` to also test:

```php
$this->actingAs($user)->get(route('admin.users'))->assertForbidden();
$this->actingAs($user)->get(route('admin.memberships'))->assertForbidden();
```

Update `'admin users can view the admin page shell'` to assert `component('admin/index')` still works.

Add new test:

```php
test('admin can access all admin pages', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get(route('admin.users'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/users/index'));

    $this->actingAs($admin)
        ->get(route('admin.memberships'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/memberships/index'));
});
```

Add new test for memberships API:

```php
test('admin can list memberships with filters', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $freeUser = User::factory()->create(['name' => 'Free User']);
    $premiumUser = User::factory()->create(['name' => 'Premium Member']);
    $premiumUser->membership()->update(['tier' => 'premium', 'status' => 'trialing']);

    $response = $this->actingAs($admin)->getJson(route('admin.memberships.index', [
        'tier' => 'premium',
    ]));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.membership.tier', 'premium');
});
```

Move the existing `'admin can filter the user membership list'` test to use `route('admin.memberships.index')` instead of `route('admin.users.index')`.

Move the existing `'admin membership updates are persisted and audited'` test to use `route('admin.memberships.update', $user)` instead of `route('admin.users.membership.update', $user)`.

- [ ] **Step 2: Run tests — expect RED**

```bash
php artisan test --compact tests/Feature/Admin/AdminConsoleTest.php
```

Expected: failures for missing routes `admin.users`, `admin.memberships`, `admin.memberships.index`, `admin.memberships.update`.

- [ ] **Step 3: Create page controllers**

```bash
php artisan make:controller Admin/AdminDashboardPageController --invokable --no-interaction
php artisan make:controller Admin/AdminUserPageController --invokable --no-interaction
php artisan make:controller Admin/AdminMembershipPageController --invokable --no-interaction
php artisan make:controller Admin/AdminMembershipController --no-interaction
```

`AdminDashboardPageController`:

```php
public function __invoke(): \Inertia\Response
{
    return inertia('admin/index');
}
```

`AdminUserPageController`:

```php
public function __invoke(): \Inertia\Response
{
    return inertia('admin/users/index');
}
```

`AdminMembershipPageController`:

```php
public function __invoke(): \Inertia\Response
{
    return inertia('admin/memberships/index');
}
```

`AdminMembershipController`: Move `index` (copy from `AdminUserController`) and `updateMembership` (renamed to `update`) from `AdminUserController`. The `index` method here is memberships-focused (always joins membership, filters by tier/status). Keep `AdminUserController@index` as a simpler user list (search only, no tier/status filters).

- [ ] **Step 4: Update routes/web.php**

```php
use App\Http\Controllers\Admin\AdminDashboardPageController;
use App\Http\Controllers\Admin\AdminMembershipPageController;
use App\Http\Controllers\Admin\AdminUserPageController;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->group(function () {
    Route::get('/', AdminDashboardPageController::class)->name('admin.index');
    Route::get('users', AdminUserPageController::class)->name('admin.users');
    Route::get('memberships', AdminMembershipPageController::class)->name('admin.memberships');
});
```

Remove the `AdminPageController` import.

- [ ] **Step 5: Update routes/api.php**

```php
use App\Http\Controllers\Admin\AdminMembershipController;
use App\Http\Controllers\Admin\AdminOverviewController;
use App\Http\Controllers\Admin\AdminUserController;

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('overview', AdminOverviewController::class)->name('admin.overview');
    Route::get('users', [AdminUserController::class, 'index'])->name('admin.users.index');
    Route::get('memberships', [AdminMembershipController::class, 'index'])->name('admin.memberships.index');
    Route::patch('users/{user}/membership', [AdminMembershipController::class, 'update'])->name('admin.memberships.update');
});
```

- [ ] **Step 6: Simplify AdminUserController**

Remove `updateMembership` method and the `UpdateMembershipRequest` / `MembershipChangeLog` imports. Keep only `index` with search filtering (name/email). Remove tier/status filter logic — that moves to `AdminMembershipController`.

- [ ] **Step 7: Delete AdminPageController**

```bash
rm app/Http/Controllers/Admin/AdminPageController.php
```

- [ ] **Step 8: Create placeholder frontend pages**

Create minimal `resources/js/pages/admin/users/index.tsx`:

```tsx
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';

export default function AdminUsers() {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Users', href: '/admin/users' },
            ]}
        >
            <Head title="Admin - Users" />
            <div className="p-4">Users page placeholder</div>
        </AppLayout>
    );
}
```

Create minimal `resources/js/pages/admin/memberships/index.tsx`:

```tsx
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';

export default function AdminMemberships() {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Memberships', href: '/admin/memberships' },
            ]}
        >
            <Head title="Admin - Memberships" />
            <div className="p-4">Memberships page placeholder</div>
        </AppLayout>
    );
}
```

- [ ] **Step 9: Run tests — expect GREEN**

```bash
php artisan test --compact tests/Feature/Admin/AdminConsoleTest.php
```

- [ ] **Step 10: Commit**

```bash
git add -A && git commit -m "refactor: restructure admin routes into multi-page layout"
```

---

## Task 3: Add `isAdminArea` Shared Prop and Update Layout Components

**Files:**

- Modify: `app/Http/Middleware/HandleInertiaRequests.php:37-126`
- Modify: `resources/js/components/app-sidebar.tsx`
- Modify: `resources/js/components/app-sidebar-header.tsx`

- [ ] **Step 1: Add `isAdminArea` to HandleInertiaRequests**

In the `share()` method, after line 41 (`$currentLedger = ...`), detect admin area:

```php
$isAdminArea = str_starts_with($request->path(), 'admin');
```

Add to the returned array:

```php
'isAdminArea' => $isAdminArea,
```

When `$isAdminArea` is true, `currentLedger` should be `null` (it already is since admin routes don't bind a `{ledger}` parameter), and ledger-specific data (availableLedgers, transactionModalData) can be skipped. Wrap those in a conditional:

```php
'availableLedgers' => $isAdminArea ? [] : $availableLedgers->values(),
'transactionModalData' => $isAdminArea ? null : Inertia::optional(function () use ($currentLedger) { ... }),
```

- [ ] **Step 2: Update AppSidebar for admin mode**

In `resources/js/components/app-sidebar.tsx`:

Import admin route helpers (will exist after build):

```tsx
import { index as adminIndex } from '@/routes/admin';
import { index as adminUsersIndex } from '@/routes/admin/users';
import { index as adminMembershipsIndex } from '@/routes/admin/memberships';
```

Also import `ArrowLeft`, `LayoutDashboard`, `Shield`, `Users as UsersIcon` from lucide.

Read `isAdminArea` from shared props:

```tsx
const { currentLedger, isAdminArea } = usePage().props as {
    currentLedger: { ... } | null;
    isAdminArea?: boolean;
};
```

When `isAdminArea` is true, use admin nav groups:

```tsx
const navGroups: NavGroup[] = isAdminArea
    ? [
          {
              label: 'Admin',
              items: [
                  { title: 'Dashboard', href: '/admin', icon: LayoutDashboard },
                  { title: 'Users', href: '/admin/users', icon: UsersIcon },
                  {
                      title: 'Memberships',
                      href: '/admin/memberships',
                      icon: Shield,
                  },
              ],
          },
      ]
    : ledgerId
      ? [
            /* existing ledger nav groups */
        ]
      : [];
```

Replace `<SidebarHeader>` content:

```tsx
<SidebarHeader>
    {isAdminArea ? (
        <div className="flex items-center gap-2 px-2 py-1.5">
            <div className="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                <Shield className="size-4" />
            </div>
            <span className="truncate text-sm font-semibold">
                Admin Console
            </span>
        </div>
    ) : (
        <LedgerSwitcher />
    )}
</SidebarHeader>
```

Replace the footer settings link section: when `isAdminArea`, show a "Back to App" link instead of Workspace Settings:

```tsx
{isAdminArea ? (
    <>
        <SidebarSeparator />
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton asChild tooltip={{ children: 'Back to App' }}>
                    <Link href="/dashboard">
                        <ArrowLeft />
                        <span>Back to App</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </>
) : settingsHref ? (
    /* existing workspace settings block */
) : null}
```

- [ ] **Step 3: Update AppSidebarHeader for admin mode**

In `resources/js/components/app-sidebar-header.tsx`:

Read `isAdminArea`:

```tsx
const { currentLedger, isAdminArea } = usePage().props;
```

Hide NotificationBell and Add Transaction when in admin:

```tsx
<div className="ml-auto flex items-center gap-2">
    {!isAdminArea && <NotificationBell />}
    {!isAdminArea && currentLedger && (
        /* existing Add Transaction button + modal */
    )}
</div>
```

- [ ] **Step 4: Run tests**

```bash
php artisan test --compact tests/Feature/Admin/AdminConsoleTest.php
```

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: add isAdminArea shared prop and admin sidebar mode"
```

---

## Task 4: Update AdminOverviewController with Comprehensive Metrics

**Files:**

- Modify: `app/Http/Controllers/Admin/AdminOverviewController.php`
- Modify: `tests/Feature/Admin/AdminConsoleTest.php`

- [ ] **Step 1: Update the overview test**

Rewrite `'admin overview returns aggregate counts without ledger data'`:

```php
test('admin overview returns aggregate counts without ledger data', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $freeUser = User::factory()->create();
    $premiumUser = User::factory()->create();

    $premiumUser->membership()->update([
        'tier' => 'premium',
        'status' => 'active',
    ]);

    $ledger = Ledger::factory()->for($premiumUser)->create([
        'name' => 'Private Household Ledger',
    ]);

    $account = $ledger->accounts()->first();

    // Create transactions: 2 today, 1 older
    Transaction::factory()->for($ledger)->create([
        'account_id' => $account->id,
        'created_at' => now(),
    ]);
    Transaction::factory()->for($ledger)->create([
        'account_id' => $account->id,
        'created_at' => now(),
    ]);
    Transaction::factory()->for($ledger)->create([
        'account_id' => $account->id,
        'created_at' => now()->subDays(10),
    ]);

    $response = $this->actingAs($admin)->getJson(route('admin.overview'));

    $response->assertOk()
        ->assertJsonPath('users.total', 3)
        ->assertJsonPath('users.verified', 3)
        ->assertJsonPath('users.new_today', 3)
        ->assertJsonPath('memberships.by_tier.free', 2)
        ->assertJsonPath('memberships.by_tier.premium', 1)
        ->assertJsonPath('ledgers.total', 1)
        ->assertJsonPath('transactions.created_today', 2)
        ->assertJsonPath('transactions.created_this_week', 3)
        ->assertJsonMissing(['Private Household Ledger']);
});
```

Add `use App\Models\Transaction;` import to the test file.

- [ ] **Step 2: Run test — expect RED**

```bash
php artisan test --compact --filter="admin overview"
```

- [ ] **Step 3: Implement updated AdminOverviewController**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOverviewController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $now = now();

        $totalUsers = User::query()->count();
        $verifiedUsers = User::query()->whereNotNull('email_verified_at')->count();
        $newToday = User::query()->whereDate('created_at', $now)->count();
        $newThisWeek = User::query()->where('created_at', '>=', $now->copy()->startOfWeek())->count();
        $activeLast7d = User::query()->where('updated_at', '>=', $now->copy()->subDays(7))->count();

        $membershipsByTier = UserMembership::query()
            ->selectRaw('tier, count(*) as total')
            ->groupBy('tier')
            ->pluck('total', 'tier')
            ->toArray();

        $totalLedgers = Ledger::query()->count();

        $txCreatedToday = Transaction::query()->whereDate('created_at', $now)->count();
        $txCreatedThisWeek = Transaction::query()->where('created_at', '>=', $now->copy()->startOfWeek())->count();

        return response()->json([
            'users' => [
                'total' => $totalUsers,
                'verified' => $verifiedUsers,
                'new_today' => $newToday,
                'new_this_week' => $newThisWeek,
                'active_last_7d' => $activeLast7d,
            ],
            'memberships' => [
                'by_tier' => $membershipsByTier,
            ],
            'ledgers' => [
                'total' => $totalLedgers,
            ],
            'transactions' => [
                'created_today' => $txCreatedToday,
                'created_this_week' => $txCreatedThisWeek,
            ],
        ]);
    }
}
```

- [ ] **Step 4: Run test — expect GREEN**

```bash
php artisan test --compact --filter="admin overview"
```

- [ ] **Step 5: Run full admin test suite**

```bash
php artisan test --compact tests/Feature/Admin/AdminConsoleTest.php
```

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat: add comprehensive metrics to admin overview endpoint"
```

---

## Task 5: Build Admin Dashboard Page (Frontend)

**Files:**

- Rewrite: `resources/js/pages/admin/index.tsx`

- [ ] **Step 1: Rewrite admin/index.tsx as dashboard**

The page should:

- Use `AppLayout` with breadcrumbs `[{ title: 'Dashboard', href: '/admin' }]`
- Fetch data from `GET /api/admin/overview` via `fetch()` with Wayfinder route helper
- Show stat cards in a responsive grid (mobile: 2 cols, md: 3 cols, lg: 4 cols):
    - Total Users, Verified Users, New Today, New This Week
    - Active (7d), Total Ledgers
    - Free Members, Premium Members
    - Transactions Today, Transactions This Week
- Each card uses `<Card>` from shadcn with a small icon, label, and large number
- Skeleton loading state while data loads

Strip out all analytics sections (`AnalyticsSection`, `AnalyticsData` type) and the memberships section (`MembershipsSection`, `EditMembershipDialog`). These move to their own pages.

```tsx
import { Head } from '@inertiajs/react';
import {
    Activity,
    CreditCard,
    LayoutDashboard,
    Receipt,
    Shield,
    TrendingUp,
    UserCheck,
    UserPlus,
    Users,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import { overview } from '@/routes/admin';

type OverviewData = {
    users: {
        total: number;
        verified: number;
        new_today: number;
        new_this_week: number;
        active_last_7d: number;
    };
    memberships: { by_tier: Record<string, number> };
    ledgers: { total: number };
    transactions: { created_today: number; created_this_week: number };
};

// ... StatCard component, loading skeleton, data display
// Uses AppLayout with breadcrumbs
```

- [ ] **Step 2: Run build to verify**

```bash
npm run build
```

- [ ] **Step 3: Commit**

```bash
git add -A && git commit -m "feat: rewrite admin dashboard page with comprehensive metrics"
```

---

## Task 6: Build Admin Users Page (Frontend)

**Files:**

- Rewrite: `resources/js/pages/admin/users/index.tsx`

- [ ] **Step 1: Build users page**

The page should:

- Use `AppLayout` with breadcrumbs `[{ title: 'Admin', href: '/admin' }, { title: 'Users', href: '/admin/users' }]`
- Fetch from `GET /api/admin/users` with search query param
- Show a searchable, paginated user list
- Mobile: card layout. Desktop: table layout.
- Columns: Name, Email, Verified (check/x badge), Membership tier badge, Joined date
- Search input with debounce
- No edit actions — browse only

- [ ] **Step 2: Run build**

```bash
npm run build
```

- [ ] **Step 3: Commit**

```bash
git add -A && git commit -m "feat: build admin users page with search and pagination"
```

---

## Task 7: Build Admin Memberships Page (Frontend)

**Files:**

- Rewrite: `resources/js/pages/admin/memberships/index.tsx`

- [ ] **Step 1: Build memberships page**

The page should:

- Use `AppLayout` with breadcrumbs `[{ title: 'Admin', href: '/admin' }, { title: 'Memberships', href: '/admin/memberships' }]`
- Fetch from `GET /api/admin/memberships` with tier/status/search filters
- Show filterable user+membership list
- Mobile: card layout. Desktop: table layout.
- Filter controls: tier select (all/free/premium), status select (all/active/trialing/past_due/canceled), search input
- Each row: Name, Email, Tier badge, Status badge, Edit button
- Edit dialog: same as current `EditMembershipDialog` — select tier, status, optional reason, PATCH to API
- Uses XSRF token pattern for PATCH requests (same as current implementation)

- [ ] **Step 2: Run build**

```bash
npm run build
```

- [ ] **Step 3: Commit**

```bash
git add -A && git commit -m "feat: build admin memberships page with filters and edit dialog"
```

---

## Task 8: Lint, Format, Build, Full Test Verification

**Files:**

- All modified files

- [ ] **Step 1: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 2: Run ESLint**

```bash
npm run lint
```

Fix any errors.

- [ ] **Step 3: Run full build**

```bash
npm run build
```

- [ ] **Step 4: Run admin tests**

```bash
php artisan test --compact tests/Feature/Admin/AdminConsoleTest.php
```

All tests must pass.

- [ ] **Step 5: Run full test suite**

```bash
php artisan test --compact
```

Check for any regressions. Note: some pre-existing failures from the `is_system` categories migration may appear — these are not caused by our changes.

- [ ] **Step 6: Final commit if any lint fixes**

```bash
git add -A && git commit -m "chore: lint and format fixes"
```
