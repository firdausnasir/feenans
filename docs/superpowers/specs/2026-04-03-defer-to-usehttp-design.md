# Defer To useHttp Migration Design

**Date:** 2026-04-03  
**Branch:** enhancement  
**Status:** Draft

---

## Summary

Replace all current frontend `Deferred` usage and backend `Inertia::defer()` / `Inertia::scroll(...)->defer()` flows with API-backed `useHttp` loaders.

- Replace `21` frontend `<Deferred>` render sites across `13` ledger pages.
- Replace `23` backend deferred data flows, including the transactions page's deferred infinite-scroll payload.
- Keep `router.get()` for page navigation only. Data fetching moves to `useHttp()` against `/api/v1/*` endpoints.
- Every deferred prop that moves to `useHttp()` gets its own API controller method or dedicated API function/variant.
- Deferred-read replacement is the required scope. Mutation conversion is limited to touched page-local write flows where optimistic UI is needed and an API endpoint already exists or is added as part of the same module change.
- Use optimistic updates only for those mutating flows, not for initial reads.
- Fix the dev-mode SSR refresh flash by making the root Blade view load the CSS entry directly.

## Current State

### Frontend

- Deferred rendering is currently used in:
    - `resources/js/pages/ledgers/accounts/index.tsx`
    - `resources/js/pages/ledgers/activity/index.tsx`
    - `resources/js/pages/ledgers/bills/index.tsx`
    - `resources/js/pages/ledgers/budgets/index.tsx`
    - `resources/js/pages/ledgers/categories/index.tsx`
    - `resources/js/pages/ledgers/dashboard.tsx`
    - `resources/js/pages/ledgers/import/index.tsx`
    - `resources/js/pages/ledgers/payees/index.tsx`
    - `resources/js/pages/ledgers/reports/index.tsx`
    - `resources/js/pages/ledgers/reports/financial-health.tsx`
    - `resources/js/pages/ledgers/reports/budget-performance.tsx`
    - `resources/js/pages/ledgers/reports/cash-flow.tsx`
    - `resources/js/pages/ledgers/tags/index.tsx`
- The transactions page does not render `<Deferred>`, but its data still comes from `Inertia::scroll(...)->defer()` in `app/Http/Controllers/Ledger/TransactionController.php` and therefore falls inside this refactor.

### Backend

- Web Inertia controllers currently expose deferred props in:
    - `AccountController`
    - `ActivityLogController`
    - `BillController`
    - `BudgetController`
    - `CategoryController`
    - `DashboardController`
    - `ImportController`
    - `PayeeController`
    - `ReportController`
    - `TagController`
    - `TransactionController`
- Existing API coverage is partial:
    - Already present and reusable for some loaders: accounts, tags, categories, payees, bills, budgets, reports, transactions, import mutations.
    - Missing or incomplete for this migration: activity list loader, import GET loaders, account page-specific loaders, and dashboard module loaders.

### SSR / CSS Flash

- `vite.config.ts` already declares both `resources/css/app.css` and `resources/js/app.tsx` as browser entries.
- `resources/views/app.blade.php` currently calls `@vite('resources/js/app.tsx')` only.
- In dev mode, the initial SSR HTML therefore contains the JS entry and Vite client scripts, but not a direct stylesheet link.
- That is the root cause of the visible unstyled sidebar links on refresh: the HTML is server-rendered, but CSS lands later through the JS entry.

## Goals

- Replace all deferred data loading with `useHttp()` calls to API endpoints.
- Remove backend `Inertia::defer()` and `Inertia::scroll(...)->defer()` usage for the affected pages.
- Keep page navigation on Inertia routes and page controllers.
- Move read requests to API endpoints with standard JSON response envelopes.
- Apply optimistic updates only to mutations that alter already-rendered local state.
- Keep SSR working and remove the dev refresh CSS flash.
- Reuse existing query / action classes instead of re-implementing business logic.
- Preserve the response contract of existing `/api/v1` consumers unless a new explicit route or route variant is added.

## Non-Goals

- Refactoring non-deferred Inertia props just because they live on the same page.
- Rebuilding the app into a purely API-driven SPA.
- Reworking unrelated routes, policies, or query logic.
- Broadly normalizing every existing API in the repository that is not touched by this migration.
- Design refreshes or UX changes beyond the requested loading and optimistic-update behavior.

## Approved Constraints

- `useHttp()` must hit API endpoints.
- `router.get()` is for page navigation only.
- Former deferred reads must not stay on Inertia partial reloads.
- Every moved deferred prop must have its own API controller method or dedicated API function variant.
- Frontend API requests should use generated route helpers rather than hardcoded `/api/v1/*` strings.
- Dashboard data must not be fetched as one grouped "dashboard" endpoint.
- Dashboard modules should reuse their owning module's API when that stays simple; `?type=dashboard` is acceptable only when it avoids excessive branching.
- If reuse would add too much conditional logic, create a dedicated module-specific API method instead.
- On the dashboard page, do not show a loading placeholder for upcoming recurring bills. Render nothing until the request finishes, then render the section only when there is actual upcoming billing data.

## Architecture Decision

### Recommended Approach

Use per-prop API-backed loaders and page-local `useHttp()` state.

- Inertia page controllers become shell-only responses.
- Deferred props are removed from those controllers.
- Each former deferred prop is loaded by a dedicated `useHttp()` instance from `/api/v1/*`.
- Existing API endpoints are reused when their payload and semantics already match the page.
- Existing API endpoints keep their current response contract for other consumers.
- New API methods or explicit variants are added inside the relevant module controller when a matching endpoint does not already exist or when changing an existing shape would be risky.
- Where a dashboard module can safely piggyback on an existing module endpoint with a small explicit parameter such as `?type=dashboard`, that is allowed.
- Where that would make the endpoint ambiguous or branch-heavy, create a dedicated module method instead.

All frontend callers should use Wayfinder-generated API route helpers for these endpoints instead of hardcoded strings so route changes remain type-safe.

This is the best fit for the approved constraints because it aligns with Inertia v3's `useHttp()` design, keeps navigation and data loading clearly separated, and avoids retaining a hybrid defer-plus-API model.

### Alternatives Rejected

#### 1. Grouped page loaders

- Rejected because the user explicitly does not want grouped dashboard fetching and wants each moved deferred prop to have its own API function.

#### 2. Keeping server-side `Inertia::defer()` and only swapping `<Deferred>` usage

- Rejected because `useHttp()` is meant to read from standalone endpoints, not to wrap deferred Inertia props.

#### 3. Large shared frontend data abstraction first

- Rejected because the request is direct: replace defer with `useHttp()`, not invent a new cross-app data layer.

## Deferred Inventory And Target APIs

### Reuse Existing API Endpoints

These deferred props can move directly onto existing API routes because their current API contract is already close enough to the page need.

| Page                         | Deferred Prop  | Existing API Reuse                                 | Notes                                                                  |
| ---------------------------- | -------------- | -------------------------------------------------- | ---------------------------------------------------------------------- |
| `tags/index`                 | `tags`         | `Api\V1\Ledger\TagController@index`                | Already returns `{ data: [...] }`.                                     |
| `payees/index`               | `payees`       | `Api\V1\Ledger\PayeeController@index`              | Already returns `{ data: [...] }`.                                     |
| `categories/index`           | `categories`   | `Api\V1\Ledger\CategoryController@index`           | Already returns `{ data: [...] }`.                                     |
| `budgets/index`              | `budgets`      | `Api\V1\Ledger\BudgetController@index`             | Already returns `{ data: [...] }`.                                     |
| `bills/index`                | `bills`        | `Api\V1\Ledger\BillController@index`               | Already returns `{ data: [...] }`.                                     |
| `reports/index`              | `report`       | `Api\V1\Ledger\ReportController@index`             | Already returns `{ data: { ... } }`.                                   |
| `reports/financial-health`   | `health`       | `Api\V1\Ledger\ReportController@financialHealth`   | Already returns `{ data: { ... } }`.                                   |
| `reports/budget-performance` | `performance`  | `Api\V1\Ledger\ReportController@budgetPerformance` | Already returns `{ data: { ... } }`.                                   |
| `reports/cash-flow`          | `cashFlow`     | `Api\V1\Ledger\ReportController@cashFlow`          | Already returns `{ data: { ... } }`.                                   |
| `transactions/index`         | `transactions` | `Api\V1\Ledger\TransactionController@index`        | Existing pagination API becomes the source of truth for the page list. |

### Add Or Extend API Methods

These deferred props need new API methods or explicit variants.

| Page             | Deferred Prop        | Module Owner           | Planned API Placement                                   | Notes                                                                                                 |
| ---------------- | -------------------- | ---------------------- | ------------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| `accounts/index` | `accounts`           | accounts               | `Api\V1\Ledger\AccountController`                       | Add dedicated method for grouped account page data instead of reusing the current flat list endpoint. |
| `accounts/index` | `accountTypes`       | accounts               | `Api\V1\Ledger\AccountController`                       | Dedicated method.                                                                                     |
| `accounts/index` | `netWorth`           | accounts               | `Api\V1\Ledger\AccountController`                       | Dedicated method.                                                                                     |
| `activity/index` | `activity`           | activity               | new `Api\V1\Ledger\ActivityLogController@index`         | Return paginated `{ data, meta }` payload for filters + paging.                                       |
| `import/index`   | `accounts`           | import/accounts        | `Api\V1\Ledger\ImportController` or account API variant | Dedicated GET loader method for import context.                                                       |
| `import/index`   | `savedMappings`      | import                 | `Api\V1\Ledger\ImportController`                        | Dedicated GET loader method.                                                                          |
| `import/index`   | `importHistory`      | import                 | `Api\V1\Ledger\ImportController`                        | Dedicated GET loader method.                                                                          |
| `dashboard`      | `dailyTrend`         | transactions/reporting | relevant module API                                     | Use a module-owned endpoint; dedicated method preferred if reuse would branch heavily.                |
| `dashboard`      | `topCategories`      | categories/reporting   | relevant module API                                     | Same rule as above.                                                                                   |
| `dashboard`      | `recentTransactions` | transactions           | relevant module API                                     | May use a dedicated recent/dashboard method instead of overloading the main index endpoint.           |
| `dashboard`      | `uncategorizedCount` | categories             | relevant module API                                     | Return `{ data: { count: number } }`.                                                                 |
| `dashboard`      | `upcomingBills`      | bills                  | relevant module API                                     | May reuse bill module with a small dashboard-type variant or dedicated `upcoming` method.             |
| `dashboard`      | `topBudgets`         | budgets                | relevant module API                                     | May reuse budget module with a small dashboard-type variant or dedicated summary method.              |

### Transactions Page Specifics

The transactions page is the only case where the backend currently uses `Inertia::scroll(...)->defer()` instead of a plain `Inertia::defer()` prop.

- The page shell will keep SSR-safe filter option props such as `accounts`, `categories`, `payees`, `tags`, and normalized `filters`.
- The `transactions` prop is removed from the Inertia response.
- The page list becomes fully API-driven through `Api\V1\Ledger\TransactionController@index`.
- Filter changes reset the local list and request page `1` from the API.
- Infinite loading appends the next API page into local state.
- Filter changes cancel any in-flight list request, clear append state, and ignore late responses from older requests.
- Infinite loading should lock while a next-page request is active so duplicate append requests cannot race.
- Any touched ancillary endpoints used from this page, such as `selectAll`, should be normalized to the standard response contract if this refactor touches them.

## API Response Contract

All new loader endpoints and all existing endpoints modified by this refactor should follow one response contract.

### Payload Responses

- Collections: `{ "data": [...] }`
- Structured single payloads: `{ "data": { ... } }`
- Paginated payloads: `{ "data": [...], "meta": { ... } }`

Existing endpoints that are already consumed elsewhere should keep their current contract. If a page needs a different shape, add an explicit route or route variant instead of mutating the established response in place.

### Empty Success Responses

- Use `204 No Content` when there is nothing meaningful to return.
- Avoid `200 {}` for newly added or normalized endpoints.

### Numeric Fidelity

- Use `JSON_PRESERVE_ZERO_FRACTION` on financial or report responses where trailing precision matters.

### Output Shaping

- Prefer existing API DTOs, resources, or transformation classes over ad hoc controller arrays when the payload is non-trivial.
- Reuse the same query / output objects used by the web layer where possible so the API and page shell do not drift apart.

## Page-Level Frontend Design

### General Pattern

For each affected page:

1. The Inertia controller renders the page shell and non-deferred props only.
2. The page creates one `useHttp()` instance per former deferred prop.
3. A mount or dependency-driven effect calls `.get()` on that instance through a Wayfinder-generated API route helper.
4. The section renders its loading UI while `processing` is true.
5. On success, the section reads from `http.data` instead of page props.
6. On failure, the section shows an inline error / retry state instead of silently staying blank.

### Request Concurrency And Stale Data

- Every loader must guard against stale responses when dependencies change quickly.
- Before issuing a replacement request for the same loader, cancel the in-flight request with `useHttp().cancel()`.
- For loaders that may still resolve out of order, keep a local request token or sequence guard and ignore responses that are no longer current.
- Infinite-scroll loaders must prevent overlapping append requests and must reset their local list state before requesting a fresh page `1` after filter changes.
- Retry actions must rerun the latest known request parameters, not a stale closure.

### Mutations

- This refactor does not require a broad migration of all write flows.
- Only page-local mutations on touched pages should move from `router.post/patch/delete` to `useHttp().post/patch/delete` when that is needed to support approved optimistic UI behavior for the data already held locally.
- Use `optimistic()` only where the page already has local data that should update instantly.
- Reads do not use optimistic behavior.
- Failed optimistic mutations rely on `useHttp()` rollback and existing toast / error handling.
- Converted mutation endpoints must return the same standard `data` envelope as other touched APIs, and validation failures should remain normal `422` JSON errors so `useHttp()` can populate `errors` consistently.

### URL State

- Page navigation continues through Inertia routes.
- Data fetching does not use `router.get()`.
- Where filter changes should remain shareable or refresh-safe, update the browser URL without turning the request into an Inertia page visit.
- Frontend callers should use Wayfinder-generated route functions for both page routes and API routes.

### Import Page

The import page should not eagerly load every API payload on initial mount.

- Step 2 loads `accounts` and `savedMappings` when the mapping step becomes active.
- Step 3 reuses the accounts loader if already loaded and does not fetch duplicate data unnecessarily.
- `importHistory` can load on mount or lazily after parse flow settles, but it remains an API-backed section instead of a deferred prop.

### Dashboard Page

- No combined dashboard loader is allowed.
- Each dashboard module owns its own `useHttp()` loader.
- `upcomingBills` renders nothing while loading.
- `upcomingBills` renders only after the request resolves and only if the payload contains upcoming, due, or missed entries.
- Existing non-deferred dashboard shell props such as `cycle`, `summary`, and `accounts` stay untouched unless implementation proves they must move for consistency with a touched module.

## Backend Controller Design

### Web Controllers

Each affected web controller should:

- stop returning `Inertia::defer()` props
- stop returning `Inertia::scroll(...)->defer()` for the transactions page
- keep only page-shell props needed for SSR, filters, forms, and navigation
- continue to authorize access exactly as they do now

### API Controllers

Each API loader method should:

- live in the module that owns the data when practical
- use route model binding and current auth / premium middleware
- reuse the existing query / action classes rather than duplicating SQL or domain logic
- return the standard response contract

### Query Reuse Rules

- Reuse existing list or report queries when the payload matches closely.
- If an endpoint would need many condition branches to support the dashboard variant, create a dedicated method instead of overloading a generic index endpoint.
- Keep the branching rule explicit and small; do not hide unrelated page behavior behind opaque query parameters.

## SSR And CSS Design

This task does not require a broader SSR rewrite.

- Keep the current Inertia v3 browser and SSR entrypoints intact unless implementation uncovers a real SSR bug.
- Fix the refresh flash in `resources/views/app.blade.php` by loading the CSS entry directly from Blade.
- Make Blade the single owner of the root stylesheet entry for this app-shell change.
- Remove the `../css/app.css` import from `resources/js/app.tsx` once Blade loads the CSS entry directly, so asset ownership is explicit and not duplicated across Blade and the JS bootstrap.
- Preferred minimal change:

```blade
@viteReactRefresh
@vite(['resources/css/app.css', 'resources/js/app.tsx'])
```

- Keep the existing `@inertiaHead` / `@inertia` structure unless another change becomes necessary during verification.
- Update the bootstrap guardrail accordingly: the root Blade view should still use one shared app bootstrap, but now it should explicitly load both the CSS and JS entries.

## Testing Strategy

Every behavioral change must be covered with focused automated tests.

### Backend Tests

- Add or update feature tests for each new or modified API loader endpoint.
- Assert the standard response contract:
    - `data` exists
    - `meta` exists for paginated loaders
    - numeric precision is preserved where expected
- Add or update page tests to confirm the corresponding Inertia page response no longer exposes the old deferred prop.
- Add a targeted test for the transactions page proving the page shell renders without `transactions` in the Inertia payload and the API endpoint supplies the list instead.

### Frontend / Integration Verification

- Verify loading states render for all moved deferred sections.
- Verify dashboard upcoming recurring shows nothing while loading and only renders after load when non-empty.
- Verify transactions infinite loading still appends correctly from the API.
- Verify rapid filter changes do not allow stale responses to overwrite newer loader state.
- Verify retry UI reruns the latest request parameters.
- Verify optimistic updates work only on mutation flows.
- Where the repository already supports browser-level testing for the touched flow, stale-request protection, retry behavior, and transactions append/reset behavior should be automated rather than left to manual-only checking.

### API Error Coverage

- Add targeted feature coverage for touched API loaders that should return `403`, `422`, or paginated `meta` structures when applicable.
- Where a mutation flow is converted to `useHttp()`, verify validation errors return JSON in a shape that `useHttp()` can consume directly.
- Touched API loaders should rely on the application's centralized JSON exception rendering for non-validation failures instead of inventing per-endpoint error payloads.
- Add at least one representative automated test covering a non-`422` API failure path for a touched loader so `useHttp()` inline error / retry UI is exercised against the real error contract.

### SSR / Architecture Guardrails

- Update the Inertia bootstrap guardrail test to assert the root Blade view loads both CSS and JS through `@vite([...])`.
- Keep the existing v3 bootstrap and SSR entry expectations intact unless a verified SSR change requires test updates.

### Required Verification Commands

- targeted `php artisan test --compact ...`
- `npm run lint`
- `vendor/bin/pint --dirty --format agent`

Additional targeted frontend or build verification should be run where the touched page warrants it.

## Risks And Mitigations

### 1. Data Shape Drift Between Web And API

Risk:

- The same conceptual data may be shaped differently between the current web queries and new API loaders.

Mitigation:

- Reuse existing query and output classes.
- Prefer one transformation source of truth per payload.

### 2. Transactions Infinite Scroll Regression

Risk:

- Replacing `Inertia::scroll(...)->defer()` with API-driven local state can break append, reset, or selection behavior.

Mitigation:

- Keep the page shell props stable.
- Convert the list logic incrementally around the existing transaction API contract.
- Add targeted tests and manual verification around filter reset, append, and bulk selection.

### 3. Dashboard Endpoint Sprawl

Risk:

- The dashboard touches several modules and can become messy if every module endpoint grows special-case branches.

Mitigation:

- Prefer dedicated module methods when branching becomes non-trivial.
- Use `?type=dashboard` only when the branch stays small and obvious.

### 4. Loading-State Regressions

Risk:

- Some pages currently rely on `Deferred` fallback boundaries that mask empty-state and error-state differences.

Mitigation:

- Explicitly implement loading, empty, success, and error states per API loader.
- Preserve the approved dashboard exception for upcoming recurring bills.

### 5. Dev Refresh FOUC Persists

Risk:

- The CSS flash could remain if the root Blade view is not updated correctly.

Mitigation:

- Treat the Blade `@vite([...])` change as part of the required scope.
- Verify the rendered dev HTML contains a stylesheet reference before claiming the issue resolved.

## Likely Files Touched

- `resources/views/app.blade.php`
- `tests/Feature/Architecture/InertiaV3BootstrapGuardrailTest.php`
- `routes/api.php`
- relevant `app/Http/Controllers/Ledger/*Controller.php` files that currently return deferred props
- relevant `app/Http/Controllers/Api/V1/Ledger/*Controller.php` files for reused or new loader methods
- affected React pages under `resources/js/pages/ledgers/**`
- targeted API and page feature tests

## Implementation Notes

- Keep changes minimal and module-local.
- Do not introduce a generic frontend data framework.
- Do not keep compatibility shims for old deferred props once the page has moved to `useHttp()`.
- Prefer small reusable helpers only where the same request lifecycle repeats naturally.

## Open Decisions Already Resolved

- Use API endpoints, not Inertia partial reloads, for moved deferred reads.
- Keep navigation on Inertia routes.
- Use optimistic updates for mutations only.
- Dashboard modules load independently.
- Upcoming recurring stays hidden until loaded and only renders when non-empty.
- CSS flash fix is part of the scope.
