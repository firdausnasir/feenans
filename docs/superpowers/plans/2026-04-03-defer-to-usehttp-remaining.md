# Defer To useHttp Remaining Work Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete the remaining post-Task-5 work by migrating dashboard and transactions read flows off deferred Inertia data, then normalize touched API contracts and finish verification.

**Architecture:** Keep Inertia page routes as the shell/navigation layer only. Dashboard modules and the transactions list should each own their own API-backed `useHttp()` loader state, using Wayfinder-generated helpers against `/api/v1/*` endpoints. Task 8 then narrows the touched API contracts and runs the full final verification set.

**Tech Stack:** Laravel 12, Inertia v3, React 19, Sanctum API routes, Wayfinder, Pest 4, Tailwind v4, ESLint, Pint

---

## Execution Context

- Active worktree: `/Users/firdausnasir/coding/feenans/.worktrees/defer-to-usehttp`
- Active branch: `defer-to-usehttp`
- Completed before this plan:
  - Task 1 shared infra / CSS flash fix
  - Task 2 accounts migration
  - Task 3 activity + import migration
  - Task 4 tags/payees/categories/budgets/bills migration
  - Task 5 reports migration, including follow-up fixes for API-only filter state and stale export state
- Remaining scope from the approved spec: Task 6 dashboard, Task 7 transactions, Task 8 final cleanup/verification

## Current State Snapshot

### Dashboard

- `app/Http/Controllers/Ledger/DashboardController.php` still returns deferred props:
  - `dailyTrend`
  - `topCategories`
  - `recentTransactions`
  - `uncategorizedCount`
  - `upcomingBills`
  - `topBudgets`
- `resources/js/pages/ledgers/dashboard.tsx` still renders all of those sections through `<Deferred>`.
- Cycle navigation still uses `router.get(..., { only: [...] })` to refresh dashboard data.
- The approved spec explicitly forbids replacing this with one grouped dashboard API loader.
- Special rule still outstanding: upcoming recurring should render nothing while loading, then render only when the API result contains actual bill data.

### Transactions

- `app/Http/Controllers/Ledger/TransactionController.php` still returns `transactions` through `Inertia::scroll(...)->defer()`.
- `resources/js/pages/ledgers/transactions/index.tsx` still depends on Inertia-provided paginated transactions and `InfiniteScroll` / scroll behavior tied to that deferred payload.
- `app/Http/Controllers/Api/V1/Ledger/TransactionController.php@index` already exists and returns `{ data, meta }`, so this should become the single source of truth for list loading.
- `selectAll`, `bulkUpdate`, and `bulkDestroy` responses are still not normalized to the `{ data }` / `204` conventions that Task 8 is meant to clean up.

### Final Cleanup

- Some touched API controllers still use mixed success contracts (`ids`, bare `response()->json()`, missing `JSON_PRESERVE_ZERO_FRACTION` in some module controllers).
- Wayfinder regeneration and final targeted verification have not yet been run after dashboard and transactions changes.

---

## Task 6: Dashboard Module Loaders

**Files:**
- Modify: `app/Http/Controllers/Ledger/DashboardController.php`
- Modify: `app/Http/Controllers/Api/V1/Ledger/BillController.php`
- Modify: `app/Http/Controllers/Api/V1/Ledger/BudgetController.php`
- Modify: `app/Http/Controllers/Api/V1/Ledger/CategoryController.php`
- Modify: `app/Http/Controllers/Api/V1/Ledger/TransactionController.php`
- Modify: `routes/api.php`
- Modify: `resources/js/pages/ledgers/dashboard.tsx`
- Modify: Wayfinder-generated API output
- Test: `tests/Feature/Ledger/DashboardPageTest.php`
- Test: `tests/Feature/DashboardUncategorizedCountTest.php`
- Test: `tests/Feature/Api/V1/Ledger/BillApiTest.php`
- Test: `tests/Feature/Api/V1/Ledger/BudgetApiTest.php`
- Test: `tests/Feature/Api/V1/Ledger/CategoryApiTest.php`
- Test: `tests/Feature/Api/V1/Ledger/TransactionApiTest.php`

- [ ] **Step 1: Write failing backend tests for shell-only dashboard props and module-owned loaders**

Cover:
- dashboard page no longer exposes deferred props for:
  - `dailyTrend`
  - `topCategories`
  - `recentTransactions`
  - `uncategorizedCount`
  - `upcomingBills`
  - `topBudgets`
- touched API endpoints expose dedicated dashboard variants or dedicated dashboard methods rather than relying on page deferred reloads
- upcoming bills loader remains module-owned under bills
- uncategorized count loader remains module-owned under categories or transactions, whichever implementation stays smallest and clearest

- [ ] **Step 2: Run the failing dashboard/backend tests**

Run:
- `APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= php artisan test --compact tests/Feature/Ledger/DashboardPageTest.php tests/Feature/DashboardUncategorizedCountTest.php tests/Feature/Api/V1/Ledger/BillApiTest.php tests/Feature/Api/V1/Ledger/BudgetApiTest.php tests/Feature/Api/V1/Ledger/CategoryApiTest.php tests/Feature/Api/V1/Ledger/TransactionApiTest.php`

Expected: FAIL on old deferred expectations / missing dashboard loader behavior.

- [ ] **Step 3: Implement minimal dashboard backend changes**

Implementation:
- remove deferred module props from `DashboardController`
- keep shell props only:
  - `cycle`
  - `summary`
  - `accounts`
- add small dashboard-specific API entrypoints only where reuse stays simple
- avoid one combined dashboard endpoint
- keep cycle offset handling available to every dashboard loader that depends on the selected cycle

- [ ] **Step 4: Regenerate Wayfinder output after route changes**

Run:
- `php artisan wayfinder:generate --with-form --no-interaction`

Expected: generated dashboard-related API helpers are available to the frontend.

- [ ] **Step 5: Write or update focused frontend helper coverage if new dashboard loader-state helpers are introduced**

Target:
- upcoming recurring hidden while loading
- upcoming recurring remains hidden when API returns empty groups
- cycle navigation updates loader params consistently without `router.get()` for read refreshes

- [ ] **Step 6: Convert dashboard page sections to independent `useHttp()` loaders**

Implementation:
- replace every `<Deferred>` dashboard section with loader-owned state
- each section loads independently
- no grouped dashboard loader
- cycle navigation updates local cycle state / URL state as needed, then refetches module loaders
- upcoming recurring:
  - render nothing while loading
  - render nothing when all bill groups are empty
  - render section only after resolved data contains `due`, `missed`, or `upcoming`
- keep writes and navigational links on existing web routes

- [ ] **Step 7: Run targeted dashboard verification**

Run:
- `APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= php artisan test --compact tests/Feature/Ledger/DashboardPageTest.php tests/Feature/DashboardUncategorizedCountTest.php tests/Feature/Api/V1/Ledger/BillApiTest.php tests/Feature/Api/V1/Ledger/BudgetApiTest.php tests/Feature/Api/V1/Ledger/CategoryApiTest.php tests/Feature/Api/V1/Ledger/TransactionApiTest.php`
- `npm run types:check`
- `npm run lint`

Expected: PASS.

---

## Task 7: Transactions API-Driven Infinite Scroll And Read Refreshes

**Files:**
- Modify: `app/Http/Controllers/Ledger/TransactionController.php`
- Modify: `app/Http/Controllers/Api/V1/Ledger/TransactionController.php`
- Modify: `resources/js/pages/ledgers/transactions/index.tsx`
- Modify: `resources/js/pages/ledgers/transactions/query-params.ts`
- Create or modify: `resources/js/pages/ledgers/transactions/*test.ts`
- Test: `tests/Feature/Ledger/TransactionPageTest.php`
- Test: `tests/Feature/Api/V1/Ledger/TransactionApiTest.php`

- [ ] **Step 1: Write failing tests for the shell-only transactions page and API-driven list contract**

Cover:
- web page no longer exposes deferred `transactions`
- web page still exposes shell props:
  - `filters`
  - `accounts`
  - `categories`
  - `payees`
  - `tags`
- API list remains `{ data, meta }`
- `meta` still includes pagination and normalized filters needed by the page

- [ ] **Step 2: Run the failing transactions tests**

Run:
- `APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= php artisan test --compact tests/Feature/Ledger/TransactionPageTest.php tests/Feature/Api/V1/Ledger/TransactionApiTest.php`

Expected: FAIL on old scroll-deferred behavior.

- [ ] **Step 3: Remove `Inertia::scroll(...)->defer()` from the web controller**

Implementation:
- keep only shell props for SSR and filter forms
- preserve current authorization and page accessibility
- do not move edit-page bootstrapping in this task

- [ ] **Step 4: Regenerate Wayfinder output if route changes were needed**

Run:
- `php artisan wayfinder:generate --with-form --no-interaction`

Expected: transaction API helpers are current.

- [ ] **Step 5: Add failing focused frontend tests for query/filter loader behavior**

Target:
- filter changes reset pagination state
- stale responses are ignored after a newer request wins
- append locking prevents duplicate next-page merges
- URL query serialization stays compatible with current server parsing

- [ ] **Step 6: Convert the transactions page list to local `useHttp()` data management**

Implementation:
- initial page load through API `index`
- next-page append through API `index`
- cancel in-flight request when filters change
- ignore late responses using request token / sequence guard
- prevent duplicate append requests while an append is active
- keep non-list mutations on existing routes unless a touched mutation must move for local consistency
- preserve mobile grouping / transfer-pair rendering / split-line rendering

- [ ] **Step 7: Run targeted transactions verification**

Run:
- `APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= php artisan test --compact tests/Feature/Ledger/TransactionPageTest.php tests/Feature/Api/V1/Ledger/TransactionApiTest.php`
- any new focused frontend test command for transaction helpers
- `npm run types:check`
- `npm run lint`

Expected: PASS.

---

## Task 8: API Contract Cleanup, Wayfinder Regeneration, And Final Verification

**Files:**
- Modify: touched API controllers
- Modify: touched API DTOs / output classes
- Modify: generated Wayfinder output
- Modify: touched feature/API tests as needed

- [ ] **Step 1: Normalize touched API success contracts**

Scope:
- touched loaders use `{ data }` or `{ data, meta }`
- touched empty-success mutation endpoints return `204` where appropriate instead of empty JSON bodies
- touched ad hoc response shapes such as `selectAll` are normalized if they are part of the migrated read/write surface
- preserve existing consumers unless an explicit touched endpoint is being intentionally normalized in this phase

- [ ] **Step 2: Decide and document the report API sign-contract follow-up**

Scope:
- review report API numeric sign behavior against project API sign rules
- either normalize the touched report API DTOs now, or explicitly defer that broader contract normalization with matching tests and notes if it would exceed approved scope
- do not leave the decision implicit

- [ ] **Step 3: Regenerate Wayfinder output**

Run:
- `php artisan wayfinder:generate --with-form --no-interaction`

Expected: generated route helpers updated for all touched API routes.

- [ ] **Step 4: Run the targeted backend suite for all touched modules**

Run:
- `APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= php artisan test --compact tests/Feature/Architecture/InertiaV3BootstrapGuardrailTest.php tests/Feature/Ledger/AccountTest.php tests/Feature/Api/V1/Ledger/AccountApiTest.php tests/Feature/Ledger/ActivityLogTest.php tests/Feature/Api/V1/Ledger/ActivityLogApiTest.php tests/Feature/Ledger/ImportTest.php tests/Feature/Ledger/ImportMappingTest.php tests/Feature/Api/V1/Ledger/ImportApiTest.php tests/Feature/Ledger/TagTest.php tests/Feature/Ledger/PayeeTest.php tests/Feature/Ledger/CategoryTest.php tests/Feature/Ledger/BudgetTest.php tests/Feature/Ledger/BillTest.php tests/Feature/Ledger/ReportTest.php tests/Feature/Api/V1/Ledger/ReportApiTest.php tests/Feature/Ledger/DashboardPageTest.php tests/Feature/DashboardUncategorizedCountTest.php tests/Feature/Api/V1/Ledger/BillApiTest.php tests/Feature/Api/V1/Ledger/BudgetApiTest.php tests/Feature/Api/V1/Ledger/CategoryApiTest.php tests/Feature/Ledger/TransactionPageTest.php tests/Feature/Api/V1/Ledger/TransactionApiTest.php`

Expected: PASS.

- [ ] **Step 5: Run frontend verification**

Run:
- `npm run types:check`
- `npm run lint`

Expected: PASS.

- [ ] **Step 6: Run PHP formatting**

Run:
- `vendor/bin/pint --dirty --format agent`

Expected: PASS.

- [ ] **Step 7: Re-run the narrowest affected backend tests if Pint changed PHP files**

Run:
- the same targeted backend command from Step 4, or the narrowest subset matching the files Pint changed

Expected: PASS.

---

## Suggested Execution Order

1. Task 6 dashboard
2. Task 7 transactions
3. Task 8 final cleanup and verification

## Notes For The Next Agent

- Continue in the worktree, not the main checkout:
  - `/Users/firdausnasir/coding/feenans/.worktrees/defer-to-usehttp`
- Task 5 is complete and verified; do not reopen it unless a later change breaks it.
- Keep TDD discipline for each remaining task.
- Re-run docs search before implementing any new `useHttp()` or API contract details.
- Do not introduce a grouped dashboard endpoint.
