# Premium Feature Gating Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Gate specific features behind a premium membership tier so free users see premium badges and get redirected to an upsell page, while premium users access everything normally.

**Architecture:** Backend middleware (`EnsurePremium`) blocks free users from premium routes (reports, bills, budgets) and redirects to `/premium`. Resource limits (workspaces, accounts) are enforced in form request `authorize()` methods and validation. The frontend reads `auth.user.membership.is_premium` from shared Inertia props to show premium badges on sidebar items and disable creation buttons.

**Tech Stack:** Laravel 12, Inertia.js v2, React 19, Tailwind CSS v4, Pest 4

**Spec:** `docs/superpowers/specs/2026-03-26-premium-feature-gating-design.md`

---

## File Structure

### New Files

| File                                             | Responsibility                                                  |
| ------------------------------------------------ | --------------------------------------------------------------- |
| `app/Http/Middleware/EnsurePremium.php`          | Middleware that redirects non-premium users to `/premium`       |
| `app/Http/Controllers/PremiumPageController.php` | Invokable controller rendering the premium upsell page          |
| `resources/js/pages/premium/index.tsx`           | Premium upsell page listing benefits with CTA placeholder       |
| `tests/Feature/PremiumGatingTest.php`            | Tests for all premium gating (middleware, limits, shared props) |

### Modified Files

| File                                            | Change                                                      |
| ----------------------------------------------- | ----------------------------------------------------------- |
| `app/Models/User.php`                           | Add `isPremium()` method                                    |
| `app/Http/Middleware/HandleInertiaRequests.php` | Share `membership` in `auth.user`                           |
| `bootstrap/app.php`                             | Register `'premium'` middleware alias                       |
| `routes/web.php`                                | Add `GET /premium` route                                    |
| `routes/ledger.php`                             | Wrap reports/bills/budgets in `middleware('premium')` group |
| `app/Http/Controllers/LedgerController.php`     | Redirect free users from create page                        |
| `app/Http/Requests/StoreLedgerRequest.php`      | Block free users with 1+ ledgers                            |
| `app/Http/Requests/StoreAccountRequest.php`     | Block free users with 7+ accounts                           |
| `app/Http/Requests/StoreTransactionRequest.php` | Block transactions to accounts beyond first 7               |
| `resources/js/types/auth.ts`                    | Add `membership` to `User` type                             |
| `resources/js/types/navigation.ts`              | Add `isPremium` to `NavItem` type                           |
| `resources/js/components/app-sidebar.tsx`       | Mark premium nav items                                      |
| `resources/js/components/nav-main.tsx`          | Render premium badges, redirect free users                  |
| `resources/js/components/ledger-switcher.tsx`   | Gate "Create workspace" for free users                      |
| `resources/js/pages/ledgers/accounts/index.tsx` | Gate "Add Account" button for free users                    |

---

### Task 1: Backend Core — User.isPremium() + Shared Props + Tests

**Files:**

- Modify: `app/Models/User.php:77-80`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php:48-57`
- Modify: `resources/js/types/auth.ts:1-13`
- Modify: `resources/js/types/global.d.ts:9-11`
- Create: `tests/Feature/PremiumGatingTest.php`

- [ ] **Step 1: Write the failing tests for isPremium() and shared props**

Create `tests/Feature/PremiumGatingTest.php`:

```php
<?php

use App\Models\Ledger;
use App\Models\User;

test('free user isPremium returns false', function () {
    $user = User::factory()->create();

    expect($user->isPremium())->toBeFalse();
});

test('premium user isPremium returns true', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);

    expect($user->fresh()->isPremium())->toBeTrue();
});

test('trialing user isPremium returns true', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'trialing']);

    expect($user->fresh()->isPremium())->toBeTrue();
});

test('canceled premium user isPremium returns false', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'canceled']);

    expect($user->fresh()->isPremium())->toBeFalse();
});

test('shared props include membership data for free user', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.membership.tier', 'free')
            ->where('auth.user.membership.is_premium', false)
        );
});

test('shared props include membership data for premium user', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.membership.tier', 'premium')
            ->where('auth.user.membership.is_premium', true)
        );
});

test('premium page renders for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('premium'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('premium/index')
        );
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=PremiumGatingTest`
Expected: FAIL — `isPremium()` method does not exist

- [ ] **Step 3: Add isPremium() to User model**

In `app/Models/User.php`, add after the `membership()` method:

```php
public function isPremium(): bool
{
    return $this->membership?->tier === 'premium'
        && in_array($this->membership->status, ['active', 'trialing']);
}
```

- [ ] **Step 4: Add membership to shared Inertia props**

In `app/Http/Middleware/HandleInertiaRequests.php`, inside the `'auth'` array where the user data is built (around line 49-57), add `'membership'` after `'is_admin'`:

```php
'membership' => [
    'tier' => $user->membership?->tier ?? 'free',
    'is_premium' => $user->isPremium(),
],
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=PremiumGatingTest`
Expected: All 6 tests PASS

- [ ] **Step 6: Update TypeScript types**

In `resources/js/types/auth.ts`, add `membership` to the `User` type (before the `[key: string]: unknown` line):

```typescript
membership: {
    tier: 'free' | 'premium';
    is_premium: boolean;
}
```

In `resources/js/types/global.d.ts`, the `auth.user` already references the `User` type, so no additional change is needed there.

- [ ] **Step 7: Run lint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 8: Commit**

```
git add -A && git commit -m "feat: add isPremium() helper and share membership data via Inertia"
```

---

### Task 2: EnsurePremium Middleware + Route Gating + Tests

**Files:**

- Create: `app/Http/Middleware/EnsurePremium.php`
- Modify: `bootstrap/app.php:38-40`
- Modify: `routes/ledger.php:32-36, 101-119, 138-147`
- Modify: `tests/Feature/PremiumGatingTest.php`

- [ ] **Step 1: Write failing tests for middleware route gating**

Append to `tests/Feature/PremiumGatingTest.php`:

```php
test('free user is redirected from reports to premium page', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger))
        ->assertRedirect(route('premium'));
});

test('premium user can access reports', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger))
        ->assertSuccessful();
});

test('free user is redirected from bills to premium page', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.bills.index', $ledger))
        ->assertRedirect(route('premium'));
});

test('premium user can access bills', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.bills.index', $ledger))
        ->assertSuccessful();
});

test('free user is redirected from budgets to premium page', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.budgets.index', $ledger))
        ->assertRedirect(route('premium'));
});

test('premium user can access budgets', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.budgets.index', $ledger))
        ->assertSuccessful();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=PremiumGatingTest`
Expected: FAIL — no redirect happening yet (routes pass through for free users)

- [ ] **Step 3: Create EnsurePremium middleware**

Create `app/Http/Middleware/EnsurePremium.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePremium
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isPremium()) {
            return redirect()->route('premium');
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Register middleware alias in bootstrap/app.php**

In `bootstrap/app.php`, add the `EnsurePremium` import at the top and add to the alias array:

```php
use App\Http\Middleware\EnsurePremium;
```

```php
$middleware->alias([
    'admin' => EnsureAdmin::class,
    'premium' => EnsurePremium::class,
]);
```

- [ ] **Step 5: Add the /premium route to web.php**

In `routes/web.php`, add the import and route. We need the controller first, so create a minimal `PremiumPageController`:

Create `app/Http/Controllers/PremiumPageController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PremiumPageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('premium/index');
    }
}
```

In `routes/web.php`, add inside the `['auth', 'verified']` middleware group:

```php
Route::get('premium', PremiumPageController::class)->name('premium');
```

Also add the import at the top:

```php
use App\Http\Controllers\PremiumPageController;
```

- [ ] **Step 6: Wrap premium routes in ledger.php**

In `routes/ledger.php`, wrap the reports, bills, and budgets route groups with a `middleware('premium')` group. The restructured section should look like:

```php
// Premium-gated features
Route::middleware('premium')->group(function () {
    // Budgets
    Route::get('ledgers/{ledger}/budgets', [BudgetController::class, 'index'])->name('ledgers.budgets.index');
    Route::post('ledgers/{ledger}/budgets', [BudgetController::class, 'store'])->name('ledgers.budgets.store');
    Route::put('ledgers/{ledger}/budgets/{budget}', [BudgetController::class, 'update'])->name('ledgers.budgets.update');
    Route::delete('ledgers/{ledger}/budgets/{budget}', [BudgetController::class, 'destroy'])->name('ledgers.budgets.destroy');

    // Bills
    Route::get('ledgers/{ledger}/bills', [BillController::class, 'index'])
        ->name('ledgers.bills.index');
    Route::get('ledgers/{ledger}/bills/create', [BillController::class, 'create'])
        ->name('ledgers.bills.create');
    Route::get('ledgers/{ledger}/bills/{bill}/edit', [BillController::class, 'edit'])
        ->name('ledgers.bills.edit');
    Route::post('ledgers/{ledger}/bills', [BillController::class, 'store'])
        ->name('ledgers.bills.store');
    Route::put('ledgers/{ledger}/bills/{bill}', [BillController::class, 'update'])
        ->name('ledgers.bills.update');
    Route::delete('ledgers/{ledger}/bills/{bill}', [BillController::class, 'destroy'])
        ->name('ledgers.bills.destroy');
    Route::patch('ledgers/{ledger}/bills/{bill}/toggle', [BillController::class, 'toggle'])
        ->name('ledgers.bills.toggle');
    Route::post('ledgers/{ledger}/bills/{bill}/pay', [BillController::class, 'pay'])
        ->name('ledgers.bills.pay');

    // Reports
    Route::get('ledgers/{ledger}/reports', [ReportController::class, 'index'])
        ->name('ledgers.reports.index');
    Route::get('ledgers/{ledger}/reports/financial-health', [ReportController::class, 'financialHealth'])
        ->name('ledgers.reports.financial-health');
    Route::get('ledgers/{ledger}/reports/budget-performance', [ReportController::class, 'budgetPerformance'])
        ->name('ledgers.reports.budget-performance');
    Route::get('ledgers/{ledger}/reports/cash-flow', [ReportController::class, 'cashFlow'])
        ->name('ledgers.reports.cash-flow');
    Route::get('ledgers/{ledger}/reports/export-pdf', [ReportController::class, 'exportPdf'])
        ->name('ledgers.reports.export-pdf');
});
```

Remove these routes from their original locations (don't duplicate them).

- [ ] **Step 7: Create a minimal premium page so the Inertia render works**

Create `resources/js/pages/premium/index.tsx` with minimal content:

```tsx
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Premium', href: '/premium' }];

export default function PremiumIndex() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Premium" />
            <div className="p-4">
                <h1>Premium placeholder</h1>
            </div>
        </AppLayout>
    );
}
```

(This will be fully built out in Task 5.)

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test --compact --filter=PremiumGatingTest`
Expected: All tests PASS

- [ ] **Step 9: Run lint**

Run: `vendor/bin/pint --dirty --format agent && npm run lint`

- [ ] **Step 10: Commit**

```
git add -A && git commit -m "feat: add EnsurePremium middleware and gate reports, bills, and budgets routes"
```

---

### Task 3: Resource Limits — Workspaces, Accounts, Transactions + Tests

**Files:**

- Modify: `app/Http/Controllers/LedgerController.php:23-31`
- Modify: `app/Http/Requests/StoreLedgerRequest.php:14-17`
- Modify: `app/Http/Requests/StoreAccountRequest.php:14-17`
- Modify: `app/Http/Requests/StoreTransactionRequest.php:116-138`
- Modify: `tests/Feature/PremiumGatingTest.php`

- [ ] **Step 1: Write failing tests for workspace limit**

Append to `tests/Feature/PremiumGatingTest.php`:

```php
test('free user with one ledger is redirected from ledger create page', function () {
    $user = User::factory()->create();
    Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.create'))
        ->assertRedirect(route('premium'));
});

test('free user with one ledger cannot create another', function () {
    $user = User::factory()->create();
    Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('ledgers.store'), [
            'name' => 'Second Workspace',
            'currency_code' => 'USD',
            'uses_seeded_categories' => true,
        ])
        ->assertForbidden();
});

test('free user with no ledger can create one', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('ledgers.store'), [
            'name' => 'First Workspace',
            'currency_code' => 'MYR',
            'uses_seeded_categories' => true,
        ])
        ->assertSessionHasNoErrors();
});

test('premium user can create multiple ledgers', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('ledgers.store'), [
            'name' => 'Second Workspace',
            'currency_code' => 'USD',
            'uses_seeded_categories' => true,
        ])
        ->assertSessionHasNoErrors();
});
```

- [ ] **Step 2: Write failing tests for account limit**

Append to `tests/Feature/PremiumGatingTest.php`:

```php
use App\Models\Account;
use App\Models\AccountType;
```

Add these at the top of the file with other imports. Then append tests:

```php
test('free user with 7 accounts cannot create an 8th', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    Account::factory()->for($ledger)->for($accountType)->count(7)->create();

    $this->actingAs($user)
        ->post(route('ledgers.accounts.store', $ledger), [
            'account_type_id' => $accountType->id,
            'name' => 'Eighth Account',
            'initial_balance' => 0,
            'include_in_totals' => true,
        ])
        ->assertForbidden();
});

test('premium user can create more than 7 accounts', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    Account::factory()->for($ledger)->for($accountType)->count(7)->create();

    $this->actingAs($user)
        ->post(route('ledgers.accounts.store', $ledger), [
            'account_type_id' => $accountType->id,
            'name' => 'Eighth Account',
            'initial_balance' => 0,
            'include_in_totals' => true,
        ])
        ->assertSessionHasNoErrors();
});
```

- [ ] **Step 3: Write failing tests for transaction gating on excess accounts**

Append to `tests/Feature/PremiumGatingTest.php`:

```php
use App\Models\Category;
```

Add at the top with other imports. Then append:

```php
test('free user cannot create transaction for account beyond first 7', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    // Create 8 accounts — the 8th should be blocked
    $accounts = Account::factory()->for($ledger)->for($accountType)->count(8)->create();
    $category = Category::factory()->for($ledger)->create(['transaction_type' => 'expense']);

    $eighthAccount = $accounts->sortBy('id')->values()->get(7);

    $this->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $eighthAccount->id,
            'category_id' => $category->id,
            'transaction_type' => 'expense',
            'amount' => 50.00,
            'transaction_date' => '2026-03-26',
        ])
        ->assertSessionHasErrors('account_id');
});

test('free user can create transaction for account within first 7', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $accounts = Account::factory()->for($ledger)->for($accountType)->count(8)->create();
    $category = Category::factory()->for($ledger)->create(['transaction_type' => 'expense']);

    $firstAccount = $accounts->sortBy('id')->values()->first();

    $this->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $firstAccount->id,
            'category_id' => $category->id,
            'transaction_type' => 'expense',
            'amount' => 50.00,
            'transaction_date' => '2026-03-26',
        ])
        ->assertSessionHasNoErrors();
});
```

- [ ] **Step 4: Run tests to verify they fail**

Run: `php artisan test --compact --filter=PremiumGatingTest`
Expected: FAIL — limits not enforced yet

- [ ] **Step 5: Implement ledger creation gate in LedgerController::create**

In `app/Http/Controllers/LedgerController.php`, modify the `create()` method:

```php
public function create(Request $request): Response|RedirectResponse
{
    if (! $request->user()->isPremium() && $request->user()->ledgers()->count() >= 1) {
        return redirect()->route('premium');
    }

    return Inertia::render('ledgers/create', [
        'defaults' => [
            'currency_code' => 'MYR',
            'uses_seeded_categories' => true,
        ],
    ]);
}
```

Update the return type import — add `RedirectResponse`:

```php
use Illuminate\Http\RedirectResponse;
```

(Already imported in the file, so no change needed.)

- [ ] **Step 6: Implement ledger creation limit in StoreLedgerRequest**

In `app/Http/Requests/StoreLedgerRequest.php`, modify `authorize()`:

```php
public function authorize(): bool
{
    $user = $this->user();

    if (! $user->isPremium() && $user->ledgers()->count() >= 1) {
        return false;
    }

    return true;
}
```

- [ ] **Step 7: Implement account creation limit in StoreAccountRequest**

In `app/Http/Requests/StoreAccountRequest.php`, modify `authorize()`:

```php
public function authorize(): bool
{
    $user = $this->user();
    $ledger = $this->route('ledger');

    if (! $user->isPremium() && $ledger->accounts()->count() >= 7) {
        return false;
    }

    return true;
}
```

- [ ] **Step 8: Implement transaction gating in StoreTransactionRequest**

In `app/Http/Requests/StoreTransactionRequest.php`, add a new closure to the existing `after()` array that checks account limits for free users. Add it before the existing splits validation closure:

```php
function (Validator $validator): void {
    $user = $this->user();

    if ($user->isPremium()) {
        return;
    }

    $ledger = $this->route('ledger');
    $allowedAccountIds = $ledger->accounts()
        ->orderBy('position')
        ->orderBy('id')
        ->limit(7)
        ->pluck('id')
        ->toArray();

    $accountId = (int) $this->input('account_id');
    if ($accountId && ! in_array($accountId, $allowedAccountIds)) {
        $validator->errors()->add('account_id', 'Upgrade to Premium to use this account.');
    }

    $toAccountId = $this->input('to_account_id');
    if ($toAccountId && ! in_array((int) $toAccountId, $allowedAccountIds)) {
        $validator->errors()->add('to_account_id', 'Upgrade to Premium to use this account.');
    }
},
```

- [ ] **Step 9: Run tests to verify they pass**

Run: `php artisan test --compact --filter=PremiumGatingTest`
Expected: All tests PASS

- [ ] **Step 10: Run full test suite to verify no regressions**

Run: `php artisan test --compact`
Expected: All tests PASS. Some existing bill/budget/report tests may now fail because those routes require premium. If so, add `$user->membership()->update(['tier' => 'premium', 'status' => 'active']);` after user creation in those tests.

- [ ] **Step 11: Run lint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 12: Commit**

```
git add -A && git commit -m "feat: enforce workspace (max 1), account (max 7), and transaction limits for free users"
```

---

### Task 4: Frontend — Sidebar Premium Badges + Nav Gating

**Files:**

- Modify: `resources/js/types/navigation.ts:9-14`
- Modify: `resources/js/components/app-sidebar.tsx:85-89, 105-109, 114-119`
- Modify: `resources/js/components/nav-main.tsx:1-47`

Skills: Activate `inertia-react-development` and `tailwindcss-development` skills.

- [ ] **Step 1: Add isPremium to NavItem type**

In `resources/js/types/navigation.ts`, add `isPremium` to the `NavItem` type:

```typescript
export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    isPremium?: boolean;
};
```

- [ ] **Step 2: Mark premium items in app-sidebar.tsx**

In `resources/js/components/app-sidebar.tsx`, add `isPremium: true` to the Reports, Recurring, and Budgets nav items:

```typescript
{
    title: 'Reports',
    href: reportsIndex.url(ledgerId),
    icon: BarChart3,
    isPremium: true,
},
```

```typescript
{
    title: 'Recurring',
    href: billsIndex.url(ledgerId),
    icon: RefreshCw,
    isPremium: true,
},
```

```typescript
{
    title: 'Budgets',
    href: budgetsIndex.url(ledgerId),
    icon: PiggyBank,
    isPremium: true,
},
```

- [ ] **Step 3: Update NavMain to render premium badges and gate navigation**

In `resources/js/components/nav-main.tsx`, update the component to:

1. Import `usePage` from `@inertiajs/react` (add to existing import)
2. Import `Badge` from `@/components/ui/badge`
3. Import `Crown` from `lucide-react`
4. Read `auth.user.membership.is_premium` from page props
5. For premium-gated items when user is NOT premium: show a badge and change href to `/premium`

The full updated component:

```tsx
import { Link, usePage } from '@inertiajs/react';
import { Crown } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavGroup } from '@/types';

export function NavMain({ groups = [] }: { groups: NavGroup[] }) {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const { auth } = usePage().props;
    const isPremiumUser = auth.user?.membership?.is_premium ?? false;

    return (
        <>
            {groups.map((group) => (
                <SidebarGroup
                    key={group.label}
                    className="mt-4 px-2 py-0 first:mt-0"
                >
                    <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
                    <SidebarMenu>
                        {group.items.map((item) => {
                            const needsUpgrade =
                                item.isPremium && !isPremiumUser;
                            const href = needsUpgrade ? '/premium' : item.href;

                            return (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={
                                            needsUpgrade
                                                ? false
                                                : item.title === 'Dashboard'
                                                  ? isCurrentUrl(item.href)
                                                  : isCurrentOrParentUrl(
                                                        item.href,
                                                    )
                                        }
                                        tooltip={{
                                            children: needsUpgrade
                                                ? `${item.title} (Premium)`
                                                : item.title,
                                        }}
                                    >
                                        <Link
                                            href={href}
                                            prefetch={!needsUpgrade}
                                        >
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                            {needsUpgrade && (
                                                <Badge
                                                    variant="secondary"
                                                    className="ml-auto gap-1 text-[10px] leading-none"
                                                >
                                                    <Crown className="size-2.5" />
                                                    Premium
                                                </Badge>
                                            )}
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            );
                        })}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </>
    );
}
```

- [ ] **Step 4: Run lint**

Run: `npm run lint`

- [ ] **Step 5: Commit**

```
git add -A && git commit -m "feat: add premium badges to sidebar nav items for free users"
```

---

### Task 5: Frontend — LedgerSwitcher Premium Gate

**Files:**

- Modify: `resources/js/components/ledger-switcher.tsx`

Skills: Activate `inertia-react-development` and `tailwindcss-development` skills.

- [ ] **Step 1: Update LedgerSwitcher to gate workspace creation**

In `resources/js/components/ledger-switcher.tsx`:

1. Add `usePage` to the existing `@inertiajs/react` import (it's already imported)
2. Import `Crown` from `lucide-react`
3. Import `Badge` from `@/components/ui/badge`
4. Read premium status from page props
5. For the "Create workspace" menu items (appears in both branches — single ledger and multiple ledgers), change the href to `/premium` and show a badge when the user is not premium and already has 1+ ledger

In the component body, after the existing hooks, add:

```tsx
const { auth } = usePage().props;
const isPremiumUser = auth.user?.membership?.is_premium ?? false;
const canCreateWorkspace = isPremiumUser || availableLedgers.length < 1;
const createWorkspaceHref = canCreateWorkspace ? create.url() : '/premium';
```

Then replace both "Create workspace" `<Link>` elements' `href` from `create.url()` to `createWorkspaceHref`, and conditionally add the badge.

For the single-ledger branch (around line 84-93):

```tsx
<DropdownMenuItem asChild>
    <Link
        href={createWorkspaceHref}
        className="flex w-full items-center gap-2"
        prefetch={canCreateWorkspace}
    >
        <Plus className="size-4" />
        Create workspace
        {!canCreateWorkspace && (
            <Badge
                variant="secondary"
                className="ml-auto gap-1 text-[10px] leading-none"
            >
                <Crown className="size-2.5" />
                Premium
            </Badge>
        )}
    </Link>
</DropdownMenuItem>
```

For the multi-ledger branch (around line 149-153):

```tsx
<DropdownMenuItem asChild>
    <Link href={createWorkspaceHref} prefetch={canCreateWorkspace}>
        Create workspace
        {!canCreateWorkspace && (
            <Badge
                variant="secondary"
                className="ml-auto gap-1 text-[10px] leading-none"
            >
                <Crown className="size-2.5" />
                Premium
            </Badge>
        )}
    </Link>
</DropdownMenuItem>
```

- [ ] **Step 2: Run lint**

Run: `npm run lint`

- [ ] **Step 3: Commit**

```
git add -A && git commit -m "feat: gate workspace creation in sidebar for free users"
```

---

### Task 6: Frontend — Account Creation Gate

**Files:**

- Modify: `resources/js/pages/ledgers/accounts/index.tsx`

Skills: Activate `inertia-react-development` and `tailwindcss-development` skills.

- [ ] **Step 1: Gate the "New Account" buttons**

In `resources/js/pages/ledgers/accounts/index.tsx`, inside the `AccountsIndex` component:

1. Import `Crown` from `lucide-react`, `Badge` from `@/components/ui/badge`, and `Link` from `@inertiajs/react`
2. Read premium status from page props:

```tsx
const { auth } = usePage<{
    accounts: AccountGroup[];
    accountTypes: AccountType[];
}>().props;
const isPremiumUser = auth.user?.membership?.is_premium ?? false;
const canCreateAccount = isPremiumUser || allAccounts.length < 7;
```

3. For the desktop "New Account" button (around line 1215-1219):

```tsx
<div className="hidden md:block">
    {canCreateAccount ? (
        <Button onClick={() => setShowCreateModal(true)}>New Account</Button>
    ) : (
        <Button variant="outline" asChild>
            <Link href="/premium" className="gap-2">
                New Account
                <Badge
                    variant="secondary"
                    className="gap-1 text-[10px] leading-none"
                >
                    <Crown className="size-2.5" />
                    Premium
                </Badge>
            </Link>
        </Button>
    )}
</div>
```

4. For the mobile "New Account" button (around line 1221-1228):

```tsx
<div className="md:hidden">
    {canCreateAccount ? (
        <Button className="w-full" onClick={() => setShowCreateModal(true)}>
            New Account
        </Button>
    ) : (
        <Button variant="outline" className="w-full" asChild>
            <Link href="/premium" className="gap-2">
                New Account
                <Badge
                    variant="secondary"
                    className="gap-1 text-[10px] leading-none"
                >
                    <Crown className="size-2.5" />
                    Premium
                </Badge>
            </Link>
        </Button>
    )}
</div>
```

5. For the empty state action (around line 1250-1259), no change needed — if there are 0 accounts, the user can still create up to 7.

- [ ] **Step 2: Run lint**

Run: `npm run lint`

- [ ] **Step 3: Commit**

```
git add -A && git commit -m "feat: gate account creation button for free users at 7 account limit"
```

---

### Task 7: Frontend — Premium Upsell Page

**Files:**

- Modify: `resources/js/pages/premium/index.tsx` (created as placeholder in Task 2)

Skills: Activate `inertia-react-development` and `tailwindcss-development` skills.

- [ ] **Step 1: Build the full premium upsell page**

Replace the placeholder content in `resources/js/pages/premium/index.tsx` with the full page. The page should:

- Use `AppLayout` with breadcrumbs
- Show a heading "Upgrade to Premium"
- List premium benefits with icons in a card grid
- Include a CTA button placeholder (disabled or showing "Coming soon" toast)
- Be mobile-first responsive
- Use existing components: `Card`, `Badge`, `Button`
- Use lucide-react icons for each benefit

```tsx
import { Head } from '@inertiajs/react';
import {
    BarChart3,
    CreditCard,
    Crown,
    Layers,
    PiggyBank,
    RefreshCw,
} from 'lucide-react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Premium', href: '/premium' }];

const premiumFeatures = [
    {
        icon: Layers,
        title: 'Unlimited Workspaces',
        description:
            'Create multiple workspaces to organize finances for different purposes — personal, business, family, and more.',
    },
    {
        icon: BarChart3,
        title: 'Financial Reports',
        description:
            'Access detailed reports including Financial Health, Budget Performance, and Cash Flow analysis with PDF export.',
    },
    {
        icon: CreditCard,
        title: 'Unlimited Accounts',
        description:
            'Add as many bank accounts, wallets, and credit cards as you need. Free plan is limited to 7 accounts.',
    },
    {
        icon: RefreshCw,
        title: 'Recurring Transactions',
        description:
            'Set up and manage recurring bills and income. Track due dates, auto-create transactions, and never miss a payment.',
    },
    {
        icon: PiggyBank,
        title: 'Budgets',
        description:
            'Create budgets by category with weekly, monthly, or yearly periods. Get notified when spending approaches your limits.',
    },
];

export default function PremiumIndex() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Premium" />

            <div className="mx-auto flex max-w-3xl flex-col gap-8 p-4 md:p-6 lg:p-8">
                {/* Header */}
                <div className="text-center">
                    <div className="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30">
                        <Crown className="size-6 text-amber-600 dark:text-amber-400" />
                    </div>
                    <h1 className="text-2xl font-bold tracking-tight md:text-3xl">
                        Upgrade to Premium
                    </h1>
                    <p className="mt-2 text-muted-foreground">
                        Unlock the full power of your financial tracking.
                    </p>
                </div>

                {/* Features */}
                <div className="grid gap-4 sm:grid-cols-2">
                    {premiumFeatures.map((feature) => (
                        <Card key={feature.title} className="gap-2 py-4">
                            <CardHeader className="pb-0">
                                <div className="flex items-center gap-3">
                                    <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                        <feature.icon className="size-4 text-primary" />
                                    </div>
                                    <CardTitle className="text-sm font-semibold">
                                        {feature.title}
                                    </CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <CardDescription className="text-xs leading-relaxed">
                                    {feature.description}
                                </CardDescription>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {/* CTA */}
                <div className="text-center">
                    <Button
                        size="lg"
                        className="gap-2"
                        onClick={() =>
                            toast.info('Premium billing coming soon!')
                        }
                    >
                        <Crown className="size-4" />
                        Get Premium
                    </Button>
                    <p className="mt-2 text-xs text-muted-foreground">
                        Billing is not yet available. Stay tuned!
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 2: Build the frontend**

Run: `npm run build`
This ensures the page compiles without errors.

- [ ] **Step 3: Run lint**

Run: `npm run lint`

- [ ] **Step 4: Commit**

```
git add -A && git commit -m "feat: build premium upsell page with feature list and CTA placeholder"
```

---

### Task 8: Fix Existing Tests + Final Verification

**Files:**

- Various test files in `tests/Feature/Ledger/`

- [ ] **Step 1: Run full test suite**

Run: `php artisan test --compact`

Check for failures. Existing tests for bills, budgets, and reports will fail because those routes now require premium. Each failing test needs the user to be set to premium.

- [ ] **Step 2: Fix failing tests**

For each failing test file, add `$user->membership()->update(['tier' => 'premium', 'status' => 'active']);` after `$user = User::factory()->create();`.

This applies to tests in:

- `tests/Feature/Ledger/BillCrudTest.php`
- `tests/Feature/Ledger/BudgetCrudTest.php` (if exists)
- Any report-related tests
- Any test that accesses bills, budgets, or report routes

- [ ] **Step 3: Run full test suite again**

Run: `php artisan test --compact`
Expected: All tests PASS

- [ ] **Step 4: Run all linters**

Run: `vendor/bin/pint --dirty --format agent && npm run lint`

- [ ] **Step 5: Commit**

```
git add -A && git commit -m "fix: update existing tests to account for premium gating"
```
