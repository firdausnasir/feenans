# Defer To useHttp Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace all deferred Inertia read flows with API-backed `useHttp` loaders, fix the dev SSR CSS flash, and preserve page behavior with targeted automated coverage.

**Architecture:** The browser keeps Inertia for page entry and navigation only. Every former deferred read is moved to a module-owned `/api/v1` endpoint and loaded from page-local `useHttp` instances using Wayfinder-generated API route helpers. The work is split into shared infrastructure, API/web-controller conversion, page-by-page frontend migration, and final verification.

**Tech Stack:** Laravel 12, Inertia v3, React 19, Sanctum API routes, Wayfinder, Pest 4, Tailwind v4, ESLint, Pint

---

## Execution Preconditions

- Browser-facing `useHttp()` reads in this refactor are intentionally routed through the existing Sanctum-protected `/api/v1` surface because that is the approved project direction for this task.
- Before broad page conversion, confirm touched browser API reads succeed under the application's current authenticated session plus Sanctum setup.
- All route changes in this plan require immediate Wayfinder regeneration in the same task before any frontend code starts consuming the new endpoint.
- Final verification must include both `npm run lint` and `npm run types:check` so generated route/helper drift is caught before completion.

## File Map

### Shared Infra And Guardrails

- Modify: `resources/views/app.blade.php`
- Modify: `resources/js/app.tsx`
- Modify: `routes/api.php`
- Modify: `tests/Feature/Architecture/InertiaV3BootstrapGuardrailTest.php`
- Modify: `tests/Feature/Architecture/DataRequestPipelineTest.php` if touched API error contract coverage fits there better than module tests

### Dashboard

- Modify: `app/Http/Controllers/Ledger/DashboardController.php`
- Modify: `app/Http/Controllers/Api/V1/Ledger/BillController.php`
- Modify: `app/Http/Controllers/Api/V1/Ledger/BudgetController.php`
- Modify: `app/Http/Controllers/Api/V1/Ledger/CategoryController.php`
- Modify: `app/Http/Controllers/Api/V1/Ledger/TransactionController.php`
- Modify: `resources/js/pages/ledgers/dashboard.tsx`
- Modify: Wayfinder-generated API route output for touched endpoints
- Modify: `tests/Feature/Ledger/DashboardPageTest.php`
- Modify: `tests/Feature/DashboardUncategorizedCountTest.php`
- Modify or create: `tests/Feature/Api/V1/Ledger/BillApiTest.php`
- Modify or create: `tests/Feature/Api/V1/Ledger/BudgetApiTest.php`
- Modify or create: `tests/Feature/Api/V1/Ledger/CategoryApiTest.php`
- Modify or create: `tests/Feature/Api/V1/Ledger/TransactionApiTest.php`

### Accounts

- Modify: `app/Http/Controllers/Ledger/AccountController.php`
- Modify: `app/Http/Controllers/Api/V1/Ledger/AccountController.php`
- Modify: `resources/js/pages/ledgers/accounts/index.tsx`
- Delete or replace: `resources/js/pages/ledgers/accounts/deferred-data.ts`
- Delete or replace: `resources/js/pages/ledgers/accounts/deferred-data.test.ts`
- Modify: Wayfinder-generated API route output for account loader endpoints
- Modify: `tests/Feature/Ledger/AccountTest.php`
- Modify: `tests/Feature/Api/V1/Ledger/AccountApiTest.php`

### Activity

- Modify: `app/Http/Controllers/Ledger/ActivityLogController.php`
- Create: `app/Http/Controllers/Api/V1/Ledger/ActivityLogController.php`
- Modify: `routes/api.php`
- Modify: `resources/js/pages/ledgers/activity/index.tsx`
- Modify: Wayfinder-generated API route output
- Modify: `tests/Feature/Ledger/ActivityLogTest.php`
- Create: `tests/Feature/Api/V1/Ledger/ActivityLogApiTest.php`

### Import

- Modify: `app/Http/Controllers/Ledger/ImportController.php`
- Modify: `app/Http/Controllers/Api/V1/Ledger/ImportController.php`
- Modify: `routes/api.php`
- Modify: `resources/js/pages/ledgers/import/index.tsx`
- Modify: Wayfinder-generated API route output
- Modify: `tests/Feature/Ledger/ImportTest.php`
- Modify: `tests/Feature/Ledger/ImportMappingTest.php`
- Modify: `tests/Feature/Api/V1/Ledger/ImportApiTest.php`

### Tags, Payees, Categories, Budgets, Bills

- Modify: `app/Http/Controllers/Ledger/TagController.php`
- Modify: `app/Http/Controllers/Ledger/PayeeController.php`
- Modify: `app/Http/Controllers/Ledger/CategoryController.php`
- Modify: `app/Http/Controllers/Ledger/BudgetController.php`
- Modify: `app/Http/Controllers/Ledger/BillController.php`
- Modify: `resources/js/pages/ledgers/tags/index.tsx`
- Modify: `resources/js/pages/ledgers/payees/index.tsx`
- Modify: `resources/js/pages/ledgers/categories/index.tsx`
- Modify: `resources/js/pages/ledgers/budgets/index.tsx`
- Modify: `resources/js/pages/ledgers/bills/index.tsx`
- Modify: `tests/Feature/Ledger/TagTest.php`
- Modify: `tests/Feature/Ledger/PayeeTest.php`
- Modify: `tests/Feature/Ledger/CategoryTest.php`
- Modify: `tests/Feature/Ledger/BudgetTest.php`
- Modify: `tests/Feature/Ledger/BillTest.php`

### Reports

- Modify: `app/Http/Controllers/Ledger/ReportController.php`
- Modify: `resources/js/pages/ledgers/reports/index.tsx`
- Modify: `resources/js/pages/ledgers/reports/financial-health.tsx`
- Modify: `resources/js/pages/ledgers/reports/budget-performance.tsx`
- Modify: `resources/js/pages/ledgers/reports/cash-flow.tsx`
- Modify: Wayfinder-generated API route output for report endpoints if needed
- Modify: `tests/Feature/Ledger/ReportTest.php`
- Modify: `tests/Feature/Api/V1/Ledger/ReportApiTest.php`

### Transactions

- Modify: `app/Http/Controllers/Ledger/TransactionController.php`
- Modify: `app/Http/Controllers/Api/V1/Ledger/TransactionController.php`
- Modify: `resources/js/pages/ledgers/transactions/index.tsx`
- Modify: `resources/js/pages/ledgers/transactions/query-params.ts`
- Modify: `tests/Feature/Ledger/TransactionPageTest.php`
- Modify: `tests/Feature/Api/V1/Ledger/TransactionApiTest.php`

---

## Task 1: Shared Infra, Route Generation, And Baseline Guardrails

**Files:**
- Modify: `resources/views/app.blade.php`
- Modify: `resources/js/app.tsx`
- Modify: `tests/Feature/Architecture/InertiaV3BootstrapGuardrailTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing tests for the root CSS entry and any new baseline API routes**

Target tests:
- `tests/Feature/Architecture/InertiaV3BootstrapGuardrailTest.php`
- add route assertions in relevant API feature tests if a new route file expectation is needed

Expected new assertions:
- root Blade view uses `@vite(['resources/css/app.css', 'resources/js/app.tsx'])`
- root Blade view no longer depends on JS-only CSS ownership

- [ ] **Step 2: Run the failing architecture test**

Run: `php artisan test --compact tests/Feature/Architecture/InertiaV3BootstrapGuardrailTest.php`

Expected: fail on old Blade/bootstrap expectation

- [ ] **Step 3: Implement the minimal shared bootstrap change**

Implementation:
- change the root Blade `@vite` call to load both CSS and JS
- remove `../css/app.css` import from `resources/js/app.tsx`
- add any placeholder API routes needed for upcoming loader actions only if required by tests at this stage

- [ ] **Step 4: Regenerate Wayfinder output if API routes changed**

Run: `php artisan wayfinder:generate --with-form --no-interaction`

Expected: generated API route helpers updated

- [ ] **Step 5: Run the architecture and type baseline again if generated routes changed**

Run:
- `php artisan test --compact tests/Feature/Architecture/InertiaV3BootstrapGuardrailTest.php`
- `npm run types:check`

Expected: PASS

- [ ] **Step 6: Run the architecture test again**

Run: `php artisan test --compact tests/Feature/Architecture/InertiaV3BootstrapGuardrailTest.php`

Expected: PASS

---

## Task 2: Accounts Read Surface Migration

**Files:**
- Modify: `app/Http/Controllers/Ledger/AccountController.php`
- Modify: `app/Http/Controllers/Api/V1/Ledger/AccountController.php`
- Modify: `routes/api.php`
- Modify: `resources/js/pages/ledgers/accounts/index.tsx`
- Modify or remove: `resources/js/pages/ledgers/accounts/deferred-data.ts`
- Modify or remove: `resources/js/pages/ledgers/accounts/deferred-data.test.ts`
- Test: `tests/Feature/Ledger/AccountTest.php`
- Test: `tests/Feature/Api/V1/Ledger/AccountApiTest.php`

- [ ] **Step 1: Write failing backend tests for account loader endpoints and web-page shell props**

Cover:
- page no longer returns deferred `accounts`, `accountTypes`, `netWorth`
- API returns loader payloads for grouped accounts, account types, and net worth using standard `data` envelopes

- [ ] **Step 2: Run the targeted failing account tests**

Run: `php artisan test --compact tests/Feature/Ledger/AccountTest.php tests/Feature/Api/V1/Ledger/AccountApiTest.php`

Expected: FAIL on missing route/controller behavior

- [ ] **Step 3: Implement minimal backend account loader methods**

Implementation:
- remove `Inertia::defer()` usage from the web controller
- add dedicated API methods for account page grouped data, account types, and net worth
- keep existing flat account API contract intact for existing consumers

- [ ] **Step 4: Regenerate Wayfinder output after account API route changes**

Run: `php artisan wayfinder:generate --with-form --no-interaction`

Expected: generated account API helpers available to the frontend

- [ ] **Step 5: Write failing frontend tests or update existing account helper tests for the new loader state shape if a helper remains**

Target:
- replace deferred-data assumptions with loader-state assumptions only if a standalone helper continues to exist

- [ ] **Step 6: Run the frontend account test file if applicable**

Run: `npm test -- resources/js/pages/ledgers/accounts/deferred-data.test.ts`

Expected: FAIL or be removed/replaced as part of the migration

- [ ] **Step 7: Implement minimal frontend account page conversion**

Implementation:
- replace `<Deferred>` with `useHttp()` loaders
- use generated API route helpers
- remove `router.reload({ only: [...] })` read refresh behavior in favor of loader refetches
- keep mutation conversion limited to touched flows that need optimistic UI

- [ ] **Step 8: Run targeted account tests**

Run:
- `php artisan test --compact tests/Feature/Ledger/AccountTest.php tests/Feature/Api/V1/Ledger/AccountApiTest.php`
- any targeted frontend account tests if retained
- `npm run types:check`

Expected: PASS

---

## Task 3: Activity And Import Read Surface Migration

**Files:**
- Modify: `app/Http/Controllers/Ledger/ActivityLogController.php`
- Create: `app/Http/Controllers/Api/V1/Ledger/ActivityLogController.php`
- Modify: `app/Http/Controllers/Ledger/ImportController.php`
- Modify: `app/Http/Controllers/Api/V1/Ledger/ImportController.php`
- Modify: `routes/api.php`
- Modify: `resources/js/pages/ledgers/activity/index.tsx`
- Modify: `resources/js/pages/ledgers/import/index.tsx`
- Test: `tests/Feature/Ledger/ActivityLogTest.php`
- Test: `tests/Feature/Api/V1/Ledger/ActivityLogApiTest.php`
- Test: `tests/Feature/Ledger/ImportTest.php`
- Test: `tests/Feature/Ledger/ImportMappingTest.php`
- Test: `tests/Feature/Api/V1/Ledger/ImportApiTest.php`

- [ ] **Step 1: Write failing tests for activity pagination API and import GET loader APIs**

Cover:
- activity page shell no longer defers `activity`
- activity API returns `{ data, meta }`
- import page shell no longer defers `accounts`, `savedMappings`, `importHistory`
- import GET loaders return standard `data` payloads

- [ ] **Step 2: Run the targeted failing tests**

Run: `php artisan test --compact tests/Feature/Ledger/ActivityLogTest.php tests/Feature/Api/V1/Ledger/ActivityLogApiTest.php tests/Feature/Ledger/ImportTest.php tests/Feature/Ledger/ImportMappingTest.php tests/Feature/Api/V1/Ledger/ImportApiTest.php`

Expected: FAIL on missing loader endpoints / old deferred props

- [ ] **Step 3: Implement minimal backend activity and import loader APIs**

Implementation:
- add activity API controller with filters + pagination
- add import loader methods for step-specific GET data
- remove corresponding deferred props from web controllers

- [ ] **Step 4: Regenerate Wayfinder output after activity/import API route changes**

Run: `php artisan wayfinder:generate --with-form --no-interaction`

Expected: generated activity/import API helpers available to the frontend

- [ ] **Step 5: Convert the activity page to `useHttp()` with cancellation and retry behavior**

Implementation:
- replace `reloadActivity` page visits with API reads
- guard against stale page/filter responses

- [ ] **Step 6: Convert the import page to step-aware `useHttp()` loaders**

Implementation:
- load step 2 and step 3 API data without duplicated requests
- keep parse/execute flows stable

- [ ] **Step 7: Run the targeted tests again**

Run the same command from Step 2

Also run: `npm run types:check`

Expected: PASS

---

## Task 4: Tags, Payees, Categories, Budgets, And Bills Read Surface Migration

**Files:**
- Modify: `app/Http/Controllers/Ledger/TagController.php`
- Modify: `app/Http/Controllers/Ledger/PayeeController.php`
- Modify: `app/Http/Controllers/Ledger/CategoryController.php`
- Modify: `app/Http/Controllers/Ledger/BudgetController.php`
- Modify: `app/Http/Controllers/Ledger/BillController.php`
- Modify: `resources/js/pages/ledgers/tags/index.tsx`
- Modify: `resources/js/pages/ledgers/payees/index.tsx`
- Modify: `resources/js/pages/ledgers/categories/index.tsx`
- Modify: `resources/js/pages/ledgers/budgets/index.tsx`
- Modify: `resources/js/pages/ledgers/bills/index.tsx`
- Test: `tests/Feature/Ledger/TagTest.php`
- Test: `tests/Feature/Ledger/PayeeTest.php`
- Test: `tests/Feature/Ledger/CategoryTest.php`
- Test: `tests/Feature/Ledger/BudgetTest.php`
- Test: `tests/Feature/Ledger/BillTest.php`

- [ ] **Step 1: Write failing page tests asserting the old deferred props are gone**

Target props:
- `tags`
- `payees`
- `categories`
- `budgets`
- `bills`

- [ ] **Step 2: Run the targeted failing tests**

Run: `php artisan test --compact tests/Feature/Ledger/TagTest.php tests/Feature/Ledger/PayeeTest.php tests/Feature/Ledger/CategoryTest.php tests/Feature/Ledger/BudgetTest.php tests/Feature/Ledger/BillTest.php`

Expected: FAIL on old deferred expectations

- [ ] **Step 3: Implement minimal web-controller removals of deferred props**

Implementation:
- keep page shell props only
- reuse current API index endpoints for reads

- [ ] **Step 4: Regenerate Wayfinder output if any module routes changed**

Run: `php artisan wayfinder:generate --with-form --no-interaction`

Expected: generated module API helpers are current

- [ ] **Step 5: Convert each page to `useHttp()` reads with inline loading / error / retry states**

Implementation order:
- tags
- payees
- categories
- budgets
- bills

- [ ] **Step 6: Run the targeted tests again**

Run the same command from Step 2 plus any affected API tests if response contracts changed

Also run: `npm run types:check`

Expected: PASS

---

## Task 5: Reports Read Surface Migration

**Files:**
- Modify: `app/Http/Controllers/Ledger/ReportController.php`
- Modify: `resources/js/pages/ledgers/reports/index.tsx`
- Modify: `resources/js/pages/ledgers/reports/financial-health.tsx`
- Modify: `resources/js/pages/ledgers/reports/budget-performance.tsx`
- Modify: `resources/js/pages/ledgers/reports/cash-flow.tsx`
- Test: `tests/Feature/Ledger/ReportTest.php`
- Test: `tests/Feature/Api/V1/Ledger/ReportApiTest.php`

- [ ] **Step 1: Write failing report page tests for deferred-prop removal**

Target props:
- `report`
- `health`
- `performance`
- `cashFlow`

- [ ] **Step 2: Run the targeted failing report tests**

Run: `php artisan test --compact tests/Feature/Ledger/ReportTest.php tests/Feature/Api/V1/Ledger/ReportApiTest.php`

Expected: FAIL on old deferred expectations

- [ ] **Step 3: Remove deferred report props from web controllers with minimal shell preservation**

Implementation:
- keep date/filter shell props needed for page render
- leave report API endpoints as the data source of truth

- [ ] **Step 4: Regenerate Wayfinder output if report routes changed**

Run: `php artisan wayfinder:generate --with-form --no-interaction`

Expected: generated report API helpers are current

- [ ] **Step 5: Convert all four report pages to `useHttp()` reads**

Implementation:
- use generated API route helpers
- preserve empty states and export links

- [ ] **Step 6: Run the targeted report tests again**

Run the same command from Step 2

Also run: `npm run types:check`

Expected: PASS

---

## Task 6: Dashboard Module Loaders

**Files:**
- Modify: `app/Http/Controllers/Ledger/DashboardController.php`
- Modify: `app/Http/Controllers/Api/V1/Ledger/BillController.php`
- Modify: `app/Http/Controllers/Api/V1/Ledger/BudgetController.php`
- Modify: `app/Http/Controllers/Api/V1/Ledger/CategoryController.php`
- Modify: `app/Http/Controllers/Api/V1/Ledger/TransactionController.php`
- Modify: `resources/js/pages/ledgers/dashboard.tsx`
- Test: `tests/Feature/Ledger/DashboardPageTest.php`
- Test: `tests/Feature/DashboardUncategorizedCountTest.php`
- Test: touched API module tests

- [ ] **Step 1: Write failing dashboard and API tests for each moved module prop**

Target module props:
- `dailyTrend`
- `topCategories`
- `recentTransactions`
- `uncategorizedCount`
- `upcomingBills`
- `topBudgets`

- [ ] **Step 2: Run the targeted failing dashboard tests**

Run: `php artisan test --compact tests/Feature/Ledger/DashboardPageTest.php tests/Feature/DashboardUncategorizedCountTest.php tests/Feature/Api/V1/Ledger/BillApiTest.php tests/Feature/Api/V1/Ledger/BudgetApiTest.php tests/Feature/Api/V1/Ledger/CategoryApiTest.php tests/Feature/Api/V1/Ledger/TransactionApiTest.php`

Expected: FAIL on missing module loaders / old deferred props

- [ ] **Step 3: Implement minimal backend dashboard module APIs**

Implementation:
- remove deferred props from dashboard page controller
- add or extend module-owned API methods
- keep each dashboard module independent
- prefer dedicated methods over heavy branching

- [ ] **Step 4: Regenerate Wayfinder output after dashboard-related API route changes**

Run: `php artisan wayfinder:generate --with-form --no-interaction`

Expected: generated dashboard-related API helpers are current

- [ ] **Step 5: Convert the dashboard page to independent `useHttp()` module loaders**

Implementation:
- do not create a grouped dashboard loader
- upcoming recurring renders nothing while loading
- upcoming recurring renders only if data is non-empty after load
- use existing summary/accounts shell props unless implementation requires otherwise

- [ ] **Step 6: Run the targeted dashboard tests again**

Run the same command from Step 2

Also run: `npm run types:check`

Expected: PASS

---

## Task 7: Transactions API-Driven Infinite Scroll And Read Refreshes

**Files:**
- Modify: `app/Http/Controllers/Ledger/TransactionController.php`
- Modify: `app/Http/Controllers/Api/V1/Ledger/TransactionController.php`
- Modify: `resources/js/pages/ledgers/transactions/index.tsx`
- Modify: `resources/js/pages/ledgers/transactions/query-params.ts`
- Test: `tests/Feature/Ledger/TransactionPageTest.php`
- Test: `tests/Feature/Api/V1/Ledger/TransactionApiTest.php`

- [ ] **Step 1: Write failing tests for the transactions page shell and API-driven list contract**

Cover:
- web page no longer returns deferred or scroll-deferred `transactions`
- API list contract remains `{ data, meta }`
- any touched ancillary read endpoints follow the standard contract

- [ ] **Step 2: Run the targeted failing transactions tests**

Run: `php artisan test --compact tests/Feature/Ledger/TransactionPageTest.php tests/Feature/Api/V1/Ledger/TransactionApiTest.php`

Expected: FAIL on old page/data assumptions

- [ ] **Step 3: Remove deferred transactions from the web page controller**

Implementation:
- keep filter option shell props
- keep the page navigable and SSR-safe

- [ ] **Step 4: Regenerate Wayfinder output if transaction routes changed**

Run: `php artisan wayfinder:generate --with-form --no-interaction`

Expected: generated transaction API helpers are current

- [ ] **Step 5: Convert the transactions page list to local `useHttp()` data management**

Implementation:
- initial load from API
- append next page from API
- cancel stale requests on filter changes
- ignore late responses
- lock during append to prevent double-fetch races

- [ ] **Step 6: Add automated coverage for stale-response and append/reset behavior where existing test tooling allows**

Target:
- browser or focused integration coverage for quick filter changes and retry behavior

- [ ] **Step 7: Run the targeted transactions tests again**

Run the same command from Step 2 plus any new browser/integration command chosen for this repo

Also run: `npm run types:check`

Expected: PASS

---

## Task 8: API Contract Cleanup, Wayfinder Regeneration, And Final Verification

**Files:**
- Modify: any touched API controllers
- Modify: generated Wayfinder output
- Test: touched feature and API tests

- [ ] **Step 1: Normalize touched API success and error behavior**

Implementation:
- ensure touched loaders use `{ data }` / `{ data, meta }`
- ensure touched empty responses use `204` where appropriate
- ensure touched loaders rely on centralized JSON exception rendering for non-`422` failures

- [ ] **Step 2: Regenerate Wayfinder output**

Run: `php artisan wayfinder:generate --with-form --no-interaction`

Expected: generated route helpers updated for every touched API route

- [ ] **Step 3: Run targeted backend tests for all touched modules**

Run a combined targeted command covering:
- architecture guardrail
- account
- activity
- import
- tag/payee/category/budget/bill
- reports
- dashboard
- transactions

Expected: PASS

- [ ] **Step 4: Run frontend verification**

Run:
- `npm run lint`
- `npm run types:check`

Expected: PASS

- [ ] **Step 5: Run PHP formatting**

Run: `vendor/bin/pint --dirty --format agent`

Expected: PASS with formatting applied if needed

- [ ] **Step 6: Re-run the narrowest affected tests after Pint if PHP files changed**

Run: the same targeted backend test command from Step 3

Expected: PASS

---

## Suggested Subagent Execution Order

1. Shared infra and guardrails
2. Accounts
3. Activity + Import
4. Tags/Payees/Categories/Budgets/Bills
5. Reports
6. Dashboard
7. Transactions
8. Final verification and cleanup

The dependencies matter:
- Shared infra first because Blade CSS ownership and route generation affect everything.
- Dashboard after its owning modules so reuse points are already stable.
- Transactions late because it has the highest concurrency risk and touches the most frontend behavior.

## Plan Review Notes

- Keep tasks small and isolated even if a single subagent handles more than one checkbox burst.
- Do not batch unrelated pages together in one code pass unless they are already sharing the same API/controller work.
- Re-run docs lookups before implementation where API or Inertia v3 `useHttp()` behavior is involved.
