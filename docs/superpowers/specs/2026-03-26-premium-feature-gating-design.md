# Premium Feature Gating

## Overview

Gate specific features behind a premium membership tier. Free users see all sidebar items but with a "Premium" badge; clicking them redirects to a dedicated upsell page. Backend middleware enforces the gate. Billing is out of scope.

## Premium-Gated Features

| Feature                            | Gate Type  | Details                                                                                                                                     |
| ---------------------------------- | ---------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| Reports (all 4 views + PDF export) | Full block | Middleware on all `/reports*` routes                                                                                                        |
| Recurring Transactions (Bills)     | Full block | Middleware on all `/bills*` routes                                                                                                          |
| Budgets                            | Full block | Middleware on all `/budgets*` routes                                                                                                        |
| Multiple Workspaces                | Limit      | Free users: max 1 ledger. Enforced in `StoreLedgerRequest` + `LedgerPolicy`                                                                 |
| Unlimited Accounts                 | Limit      | Free users: max 7 accounts. Enforced in `StoreAccountRequest`. Transactions to accounts beyond first 7 blocked in `StoreTransactionRequest` |

## Backend Changes

### 1. User Model Helpers

Add to `App\Models\User`:

```php
public function isPremium(): bool
{
    return $this->membership?->tier === 'premium'
        && in_array($this->membership->status, ['active', 'trialing']);
}
```

The `membership` relationship already exists and is auto-created on user registration with `tier: 'free', status: 'active'`.

### 2. EnsurePremium Middleware

New file: `app/Http/Middleware/EnsurePremium.php`

- Checks `$request->user()->isPremium()`
- If not premium: redirect to `/premium` (Inertia requests will follow the redirect)
- Register as alias `'premium'` in `bootstrap/app.php`

### 3. Route Gating

In `routes/ledger.php`, wrap premium routes in a `middleware('premium')` group:

```
Reports:    GET  ledgers/{ledger}/reports*          (all 5 report routes)
Bills:      GET  ledgers/{ledger}/bills*             (all bill routes)
            POST ledgers/{ledger}/bills*
            PUT  ledgers/{ledger}/bills*
            DELETE ledgers/{ledger}/bills*
            PATCH  ledgers/{ledger}/bills*
Budgets:    GET  ledgers/{ledger}/budgets*           (all budget routes)
            POST ledgers/{ledger}/budgets*
            PUT  ledgers/{ledger}/budgets*
            DELETE ledgers/{ledger}/budgets*
```

### 4. Workspace Limit (Max 1 for Free Users)

In `StoreLedgerRequest::authorize()`:

```php
public function authorize(): bool
{
    $user = $this->user();
    if (!$user->isPremium() && $user->ledgers()->count() >= 1) {
        return false;
    }
    return true;
}
```

The `authorize()` returning false will produce a 403. The frontend prevents this from being reached by hiding the "Create workspace" action behind a premium gate in `LedgerSwitcher`.

Additionally, in `LedgerController::create`, redirect free users who already have 1+ ledgers to `/premium` for a smoother UX (prevents them from seeing the form only to get a 403 on submit).

### 5. Account Limit (Max 7 for Free Users)

In `StoreAccountRequest::authorize()`:

```php
public function authorize(): bool
{
    $user = $this->user();
    $ledger = $this->route('ledger');
    if (!$user->isPremium() && $ledger->accounts()->count() >= 7) {
        return false;
    }
    return true;
}
```

### 6. Transaction Gating on Excess Accounts

In `StoreTransactionRequest::after()`, add a check: if the user is not premium, get the IDs of their first 7 accounts (ordered by `position` then `id`). If the `account_id` or `to_account_id` is not in that set, add a validation error.

### 7. Shared Inertia Props

In `HandleInertiaRequests::share()`, add membership data to the `auth.user` array:

```php
'membership' => [
    'tier' => $user->membership?->tier ?? 'free',
    'is_premium' => $user->isPremium(),
],
```

### 8. Premium Page Controller

New invokable controller: `App\Http\Controllers\PremiumPageController`

- Route: `GET /premium` (auth + verified middleware)
- Renders `premium/index` Inertia page
- No special props needed (tier info comes from shared props)

## Frontend Changes

### 1. TypeScript Types

Update `resources/js/types/auth.ts`:

```typescript
export type User = {
    // ...existing fields
    membership: {
        tier: 'free' | 'premium';
        is_premium: boolean;
    };
};
```

Update `resources/js/types/global.d.ts` -- the `auth.user` shared prop already uses the `User` type, so no separate change needed.

Update `resources/js/types/navigation.ts`:

```typescript
export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    isPremium?: boolean; // new
};
```

### 2. Sidebar - NavItem Premium Badges

In `app-sidebar.tsx`, mark premium items:

```typescript
{ title: 'Reports', href: reportsIndex.url(ledgerId), icon: BarChart3, isPremium: true },
{ title: 'Recurring', href: billsIndex.url(ledgerId), icon: RefreshCw, isPremium: true },
{ title: 'Budgets', href: budgetsIndex.url(ledgerId), icon: PiggyBank, isPremium: true },
```

### 3. NavMain Component

In `nav-main.tsx`:

- Read `auth.user.membership.is_premium` from `usePage().props`
- For items with `isPremium: true` where the user is NOT premium:
    - Show a small "Premium" badge (e.g., a `<Badge>` or styled `<span>`) next to the title
    - Change the `href` to `/premium` instead of the feature route
- For premium users: render normally, no badge

### 4. LedgerSwitcher Premium Gate

In `ledger-switcher.tsx`:

- Read `auth.user.membership.is_premium` from `usePage().props`
- If user is NOT premium and `availableLedgers.length >= 1`:
    - On the "Create workspace" dropdown item, show a "Premium" badge
    - Change its `href` to `/premium`

### 5. Account Creation Gate

In `resources/js/pages/ledgers/accounts/index.tsx`:

- Read the user's premium status from shared props
- If not premium and account count >= 7, disable the "Add Account" button and show a premium badge
- Optionally show inline text: "Free accounts limit reached"

### 6. Premium Upsell Page

New page: `resources/js/pages/premium/index.tsx`

Layout: Uses the app sidebar layout (sidebar remains visible with all items).

Content:

- Heading: "Upgrade to Premium"
- List of premium benefits with icons:
    - Unlimited workspaces
    - Financial reports (Financial Health, Budget Performance, Cash Flow)
    - Unlimited accounts (free: max 7)
    - Recurring transactions
    - Budgets
- CTA placeholder (button that does nothing or shows "Coming soon" toast, since billing is out of scope)

## Testing

### Feature Tests

1. **EnsurePremium middleware**: Free user gets redirected to `/premium` on report/bill/budget routes. Premium user passes through.
2. **Workspace limit**: Free user with 1 ledger gets redirected from create page and cannot POST another (403). Premium user can.
3. **Account limit**: Free user with 7 accounts cannot create an 8th (403). Premium user can.
4. **Transaction gating**: Free user cannot create a transaction targeting account #8+. Premium user can.
5. **Premium page**: Renders correctly for both free and premium users.
6. **Shared props**: `auth.user.membership` is present with correct tier.

### What's NOT Tested

- Frontend badge rendering (no browser tests in scope)
- Billing (explicitly out of scope)

## Files Changed (Summary)

### New Files

- `app/Http/Middleware/EnsurePremium.php`
- `app/Http/Controllers/PremiumPageController.php`
- `resources/js/pages/premium/index.tsx`

### Modified Files

- `app/Models/User.php` -- add `isPremium()`
- `app/Http/Middleware/HandleInertiaRequests.php` -- add membership to shared props
- `bootstrap/app.php` -- register `premium` middleware alias
- `routes/web.php` -- add `/premium` route
- `routes/ledger.php` -- wrap premium routes in middleware group
- `app/Http/Controllers/LedgerController.php` -- redirect free users from create page
- `app/Http/Requests/StoreLedgerRequest.php` -- add ledger count check in `authorize()`
- `app/Http/Requests/StoreAccountRequest.php` -- add account count check in `authorize()`
- `app/Http/Requests/StoreTransactionRequest.php` -- add account limit check in `after()`
- `resources/js/types/auth.ts` -- add `membership` to `User` type
- `resources/js/types/navigation.ts` -- add `isPremium` to `NavItem`
- `resources/js/components/app-sidebar.tsx` -- mark premium items
- `resources/js/components/nav-main.tsx` -- render premium badges, change hrefs for free users
- `resources/js/components/ledger-switcher.tsx` -- gate "Create workspace" for free users
- `resources/js/pages/ledgers/accounts/index.tsx` -- gate "Add Account" for free users

## Edge Cases

- **Downgrade**: A premium user who downgrades keeps all data. They can view existing accounts/workspaces but cannot create new ones beyond limits. Existing bills/budgets become inaccessible via routes (middleware blocks). The data remains in the database.
- **Trialing status**: Treated as premium (full access).
- **No membership record**: Defensive -- `isPremium()` returns false if `membership` is null. The `booted()` hook on User ensures every user gets a membership record, but the code handles the edge case.
