# Remaining Shared-Core Ledger Modules Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Finish the shared-core rollout after Phase 5 by migrating Accounts, Bills, Transactions, Import, and Reports to the dual-surface Inertia web plus Sanctum API architecture, then optionally finish contract-tooling cleanup.

**Architecture:** This plan assumes Phases 0 through 5 are already complete, including the shared Data/action/domain-exception scaffolding, token auth surface, and migrated Tags, Payees, Categories, and Budgets. Remaining work must preserve the current browser controller class paths, keep browser pages pure Inertia, move read composition into query actions, move write orchestration into top-level use-case actions, and add API V1 controllers that reuse the same shared core. Cross-module spillover is explicit: Dashboard depends on Accounts and Bills, Import depends on Transactions, and Reports depend on Budgets, Accounts, Bills, and Transactions.

**Tech Stack:** Laravel 12, Inertia v2, React 19, Sanctum 4, Wayfinder, Pest 4, existing shared-core Data/actions architecture from Phases 1 to 5

---

## Remaining Migration Scope

Phase 5 is complete for Tags, Payees, Categories, and Budgets.

What remains to migrate:

1. Accounts
2. Bills
3. Transactions
4. Import
5. Reports
6. Optional Phase 7 contract tooling and cleanup

Do not report Phase 5 as still in flight once the Budget changes are committed. From this point onward, every phase-complete update to the user must explicitly restate the remaining list above, minus anything finished.

---

## File Map

### Accounts

- Modify: `app/Http/Controllers/Ledger/AccountController.php`
- Modify: `app/Http/Controllers/Ledger/DashboardController.php`
- Create: `app/Http/Controllers/Api/V1/Ledger/AccountController.php`
- Create: `app/Data/Accounts/Input/GetAccountPageData.php`
- Create: `app/Data/Accounts/Input/StoreAccountData.php`
- Create: `app/Data/Accounts/Input/UpdateAccountData.php`
- Create: `app/Data/Accounts/Input/AdjustAccountBalanceData.php`
- Create: `app/Data/Accounts/Input/ReorderAccountsData.php`
- Create: `app/Data/Accounts/Output/Web/AccountData.php`
- Create: `app/Data/Accounts/Output/Web/AccountGroupData.php`
- Create: `app/Data/Accounts/Output/Web/AccountNetWorthData.php`
- Create: `app/Data/Accounts/Output/Web/AccountPageData.php`
- Create: `app/Data/Accounts/Output/Api/AccountData.php`
- Create: `app/Data/Accounts/Output/Api/AccountListData.php`
- Create: `app/Data/Accounts/Output/AccountExportRowData.php`
- Create: `app/Actions/Accounts/Queries/GetAccountPageQuery.php`
- Create: `app/Actions/Accounts/Queries/ListAccountsByTotalsQuery.php`
- Create: `app/Actions/Accounts/Queries/ListAccountsByTypeQuery.php`
- Create: `app/Actions/Accounts/Queries/GetNetWorthQuery.php`
- Create: `app/Actions/Accounts/Queries/ExportAccountTransactionsQuery.php`
- Create: `app/Data/Dashboard/Output/Web/DashboardPageData.php`
- Create: `app/Actions/Dashboard/Queries/GetDashboardPageQuery.php`
- Create: `app/Actions/Accounts/UseCases/StoreAccountAction.php`
- Create: `app/Actions/Accounts/UseCases/UpdateAccountAction.php`
- Create: `app/Actions/Accounts/UseCases/DeleteAccountAction.php`
- Create: `app/Actions/Accounts/UseCases/ReorderAccountsAction.php`
- Create: `app/Actions/Accounts/UseCases/AdjustAccountBalanceAction.php`
- Modify: `routes/api.php`
- Modify: `resources/js/pages/ledgers/accounts/index.tsx`
- Modify: `resources/js/pages/ledgers/accounts/deferred-data.ts`
- Modify: `resources/js/pages/ledgers/accounts/deferred-data.test.ts`
- Modify: `tests/Feature/Ledger/AccountTest.php`
- Modify: `tests/Feature/Ledger/AccountCrudTest.php`
- Modify: `tests/Feature/Ledger/AccountExportTest.php`
- Create: `tests/Feature/Api/V1/Ledger/AccountApiTest.php`

### Bills

- Modify: `app/Http/Controllers/Ledger/BillController.php`
- Create: `app/Http/Controllers/Api/V1/Ledger/BillController.php`
- Create: `app/Data/Bills/Input/GetBillIndexPageData.php`
- Create: `app/Data/Bills/Input/GetBillFormPageData.php`
- Create: `app/Data/Bills/Input/StoreBillData.php`
- Create: `app/Data/Bills/Input/UpdateBillData.php`
- Create: `app/Data/Bills/Input/PayBillData.php`
- Create: `app/Data/Bills/Output/Web/BillData.php`
- Create: `app/Data/Bills/Output/Web/BillAccountOptionData.php`
- Create: `app/Data/Bills/Output/Web/BillPageData.php`
- Create: `app/Data/Bills/Output/Web/BillHistoryTransactionData.php`
- Create: `app/Data/Bills/Output/Api/BillData.php`
- Create: `app/Data/Bills/Output/Api/BillListData.php`
- Create: `app/Actions/Bills/Queries/GetBillIndexPageQuery.php`
- Create: `app/Actions/Bills/Queries/GetBillFormPageQuery.php`
- Create: `app/Actions/Bills/Queries/ListBillsQuery.php`
- Create: `app/Actions/Bills/Queries/ListUpcomingBillsQuery.php`
- Create: `app/Actions/Bills/Queries/GetBillAccountOptionsQuery.php`
- Create: `app/Actions/Bills/Queries/GetBillMissedCyclesQuery.php`
- Create: `app/Actions/Bills/UseCases/StoreBillAction.php`
- Create: `app/Actions/Bills/UseCases/UpdateBillAction.php`
- Create: `app/Actions/Bills/UseCases/DeleteBillAction.php`
- Create: `app/Actions/Bills/UseCases/ToggleBillAction.php`
- Create: `app/Actions/Bills/UseCases/PayBillAction.php`
- Create: `app/Actions/Bills/UseCases/ProcessAutoBillsAction.php`
- Modify: `app/Services/BillService.php` or delete it once every consumer has moved
- Modify: `app/Http/Controllers/Ledger/DashboardController.php`
- Modify: `routes/console.php`
- Modify: `routes/api.php`
- Modify: `resources/js/pages/ledgers/bills/index.tsx`
- Modify: `resources/js/pages/ledgers/bills/create.tsx`
- Modify: `resources/js/pages/ledgers/bills/edit.tsx`
- Modify: `tests/Feature/Ledger/BillTest.php`
- Modify: `tests/Feature/Ledger/BillCrudTest.php`
- Modify: `tests/Feature/Ledger/BillIndexAccountAndHistoryTest.php`
- Modify: `tests/Feature/Ledger/BillServiceTest.php`
- Modify: `tests/Feature/Ledger/ExportTest.php`
- Modify: `tests/Feature/Ledger/DashboardPageTest.php`
- Create: `tests/Feature/Api/V1/Ledger/BillApiTest.php`

### Transactions

- Modify: `app/Http/Controllers/Ledger/TransactionController.php`
- Create: `app/Http/Controllers/Api/V1/Ledger/TransactionController.php`
- Create: `app/Http/Controllers/Api/V1/Ledger/TransactionAttachmentController.php`
- Create: `app/Data/Transactions/Input/GetTransactionIndexData.php`
- Create: `app/Data/Transactions/Input/StoreTransactionData.php`
- Create: `app/Data/Transactions/Input/UpdateTransactionData.php`
- Create: `app/Data/Transactions/Input/BulkUpdateTransactionsData.php`
- Create: `app/Data/Transactions/Input/BulkDestroyTransactionsData.php`
- Create: `app/Data/Transactions/Input/SelectAllTransactionsData.php`
- Create: `app/Data/Transactions/Input/ExportTransactionsData.php`
- Create: `app/Data/Transactions/Input/StoreTransactionAttachmentsData.php`
- Create: `app/Data/Transactions/Input/DeleteTransactionAttachmentData.php`
- Create: `app/Data/Transactions/Output/Web/TransactionData.php`
- Create: `app/Data/Transactions/Output/Web/TransactionIndexPageData.php`
- Create: `app/Data/Transactions/Output/Web/TransactionEditPageData.php`
- Create: `app/Data/Transactions/Output/Web/TransactionFiltersData.php`
- Create: `app/Data/Transactions/Output/Api/TransactionData.php`
- Create: `app/Data/Transactions/Output/Api/TransactionListData.php`
- Create: `app/Data/Transactions/Output/TransactionExportRowData.php`
- Create: `app/Actions/Transactions/Queries/GetTransactionIndexPageQuery.php`
- Create: `app/Actions/Transactions/Queries/GetTransactionEditPageQuery.php`
- Create: `app/Actions/Transactions/Queries/ListTransactionsQuery.php`
- Create: `app/Actions/Transactions/Queries/ListTransactionAccountsQuery.php`
- Create: `app/Actions/Transactions/Queries/ListTransactionCategoriesQuery.php`
- Create: `app/Actions/Transactions/Queries/ListTransactionPayeesQuery.php`
- Create: `app/Actions/Transactions/Queries/ListTransactionTagsQuery.php`
- Create: `app/Actions/Transactions/Queries/NormalizeTransactionFiltersQuery.php`
- Create: `app/Actions/Transactions/Queries/SelectAllTransactionIdsQuery.php`
- Create: `app/Actions/Transactions/Queries/ExportTransactionsQuery.php`
- Create: `app/Actions/Transactions/Queries/LoadTransferPairRelationsQuery.php`
- Create: `app/Actions/Transactions/UseCases/StoreTransactionAction.php`
- Create: `app/Actions/Transactions/UseCases/UpdateTransactionAction.php`
- Create: `app/Actions/Transactions/UseCases/DeleteTransactionAction.php`
- Create: `app/Actions/Transactions/UseCases/BulkUpdateTransactionsAction.php`
- Create: `app/Actions/Transactions/UseCases/BulkDestroyTransactionsAction.php`
- Create: `app/Actions/Transactions/UseCases/StoreTransactionAttachmentsAction.php`
- Create: `app/Actions/Transactions/UseCases/DeleteTransactionAttachmentAction.php`
- Create: `app/Actions/Transactions/UseCases/CelebrateFirstTransactionAction.php`
- Modify: `app/Services/TransactionService.php` or delete it once every consumer has moved
- Modify: `app/Http/Controllers/Ledger/AttachmentController.php`
- Modify: `app/Http/Controllers/Ledger/ImportController.php`
- Modify: `app/Http/Controllers/Ledger/BudgetController.php` only if remaining transaction write paths still call old service helpers
- Modify: `routes/api.php`
- Modify: `resources/js/pages/ledgers/transactions/index.tsx`
- Modify: `resources/js/pages/ledgers/transactions/edit.tsx`
- Modify: `resources/js/pages/ledgers/transactions/query-params.ts`
- Modify: `resources/js/pages/ledgers/transactions/query-params.test.ts`
- Modify: `resources/js/pages/ledgers/transactions/mobile-transaction-row-data.ts`
- Modify: `resources/js/pages/ledgers/transactions/mobile-transaction-row-data.test.ts`
- Modify: `resources/js/pages/ledgers/transactions/mobile-transaction-groups.ts`
- Modify: `resources/js/pages/ledgers/transactions/mobile-transaction-groups.test.ts`
- Modify: `resources/js/pages/ledgers/transactions/mobile-transaction-list.tsx`
- Modify: `tests/Feature/Ledger/TransactionTest.php`
- Modify: `tests/Feature/Ledger/TransactionCrudTest.php`
- Modify: `tests/Feature/Ledger/TransactionPageTest.php`
- Modify: `tests/Feature/Ledger/TransactionSearchTest.php`
- Modify: `tests/Feature/Ledger/TransactionTypeConversionTest.php`
- Modify: `tests/Feature/Ledger/TransactionBulkActionTest.php`
- Modify: `tests/Feature/Ledger/TransactionAmountGuardrailTest.php`
- Modify: `tests/Feature/Ledger/SplitTransactionTest.php`
- Modify: `tests/Feature/Ledger/AttachmentTest.php`
- Modify: `tests/Feature/Ledger/TransferAttachmentTest.php`
- Modify: `tests/Feature/Ledger/TransactionServiceTest.php`
- Modify: `tests/Feature/Ledger/TransactionSplitServiceTest.php`
- Create: `tests/Feature/Api/V1/Ledger/TransactionApiTest.php`

### Import

- Modify: `app/Http/Controllers/Ledger/ImportController.php`
- Create: `app/Http/Controllers/Api/V1/Ledger/ImportController.php`
- Create: `app/Data/Imports/Input/ParseImportData.php`
- Create: `app/Data/Imports/Input/GetImportPageData.php`
- Create: `app/Data/Imports/Input/StoreImportData.php`
- Create: `app/Data/Imports/Input/StoreImportMappingData.php`
- Create: `app/Data/Imports/Output/PendingImportHandleData.php`
- Create: `app/Data/Imports/Output/Web/ImportParseResultData.php`
- Create: `app/Data/Imports/Output/Web/ImportHistoryData.php`
- Create: `app/Data/Imports/Output/Web/ImportMappingData.php`
- Create: `app/Data/Imports/Output/Web/ImportPageData.php`
- Create: `app/Data/Imports/Output/Api/ImportParseResultData.php`
- Create: `app/Data/Imports/Output/Api/ImportExecutionData.php`
- Create: `app/Actions/Imports/Queries/GetImportPageQuery.php`
- Create: `app/Actions/Imports/Queries/DetectImportBankFormatQuery.php`
- Create: `app/Actions/Imports/Queries/ParseImportPreviewQuery.php`
- Create: `app/Actions/Imports/UseCases/ParseImportAction.php`
- Create: `app/Actions/Imports/UseCases/CreatePendingImportHandleAction.php`
- Create: `app/Actions/Imports/UseCases/ExecuteImportAction.php`
- Create: `app/Actions/Imports/UseCases/StoreImportMappingAction.php`
- Create: `app/Actions/Imports/UseCases/DeleteImportMappingAction.php`
- Create: `app/Actions/Imports/UseCases/ResolvePendingImportHandleAction.php`
- Modify: `routes/api.php`
- Modify: `resources/js/pages/ledgers/import/index.tsx`
- Modify: `tests/Feature/Ledger/ImportTest.php`
- Modify: `tests/Feature/Ledger/ImportMappingTest.php`
- Create: `tests/Feature/Api/V1/Ledger/ImportApiTest.php`

### Reports

- Modify: `app/Http/Controllers/Ledger/ReportController.php`
- Create: `app/Http/Controllers/Api/V1/Ledger/ReportController.php`
- Create: `app/Data/Reports/Input/ReportFiltersData.php`
- Create: `app/Data/Reports/Input/GetFinancialHealthPageData.php`
- Create: `app/Data/Reports/Input/GetBudgetPerformancePageData.php`
- Create: `app/Data/Reports/Input/GetCashFlowPageData.php`
- Create: `app/Data/Reports/Input/BudgetPerformanceFiltersData.php`
- Create: `app/Data/Reports/Input/ExportReportPdfData.php`
- Create: `app/Data/Reports/Output/Web/SpendingReportData.php`
- Create: `app/Data/Reports/Output/Web/FinancialHealthReportData.php`
- Create: `app/Data/Reports/Output/Web/BudgetPerformanceReportData.php`
- Create: `app/Data/Reports/Output/Web/CashFlowReportData.php`
- Create: `app/Data/Reports/Output/Web/ReportDateRangeData.php`
- Create: `app/Data/Reports/Output/Api/SpendingReportData.php`
- Create: `app/Data/Reports/Output/Api/FinancialHealthReportData.php`
- Create: `app/Data/Reports/Output/Api/BudgetPerformanceReportData.php`
- Create: `app/Data/Reports/Output/Api/CashFlowReportData.php`
- Create: `app/Actions/Reports/Queries/GetSpendingReportPageQuery.php`
- Create: `app/Actions/Reports/Queries/GetFinancialHealthPageQuery.php`
- Create: `app/Actions/Reports/Queries/GetBudgetPerformancePageQuery.php`
- Create: `app/Actions/Reports/Queries/GetCashFlowPageQuery.php`
- Create: `app/Actions/Reports/Queries/GetSpendingReportDataQuery.php`
- Create: `app/Actions/Reports/Queries/GetFinancialHealthReportDataQuery.php`
- Create: `app/Actions/Reports/Queries/GetBudgetPerformanceReportDataQuery.php`
- Create: `app/Actions/Reports/Queries/GetCashFlowReportDataQuery.php`
- Create: `app/Actions/Reports/Queries/GetReportComparisonQuery.php`
- Create: `app/Actions/Reports/Queries/GetReportPdfPayloadQuery.php`
- Modify: `app/Services/ReportService.php` or delete it once every consumer has moved
- Modify: `resources/views/reports/monthly-pdf.blade.php`
- Modify: `routes/api.php`
- Modify: `resources/js/pages/ledgers/reports/index.tsx`
- Modify: `resources/js/pages/ledgers/reports/financial-health.tsx`
- Modify: `resources/js/pages/ledgers/reports/budget-performance.tsx`
- Modify: `resources/js/pages/ledgers/reports/cash-flow.tsx`
- Modify: `tests/Feature/Ledger/ReportTest.php`
- Modify: `tests/Feature/Ledger/ReportComparisonTest.php`
- Modify: `tests/Feature/Ledger/ReportPdfExportTest.php`
- Create: `tests/Feature/Api/V1/Ledger/ReportApiTest.php`

### Optional Phase 7 Tooling

- Modify: `composer.json`
- Create: `app/Providers/TypeScriptTransformerServiceProvider.php`
- Modify: `bootstrap/providers.php`
- Modify: generated output under the existing TypeScript destination chosen by the repo
- Optionally modify: `resources/js/types/index.ts`
- Optionally modify: `resources/js/types/ledger.ts`
- Create: `tests/Feature/Architecture/TypeContractGenerationTest.php`

---

## Module Order And Dependency Rules

1. Accounts first, because Dashboard still depends on account grouping and net-worth composition.
2. Bills second, because Dashboard and scheduled auto-processing still depend on bill logic.
3. Transactions third, because Import and large parts of Reports depend on stable transaction query and write paths.
4. Import fourth, because it should consume the migrated transaction write core instead of freezing old service assumptions in place.
5. Reports fifth, because by then Accounts, Bills, Budgets, and Transactions read models are stable.
6. Optional Phase 7 last.

Do not start Import before the transaction use-case and query layer is stable.

Do not delete `BillService`, `TransactionService`, or `ReportService` until all direct callers have been moved and their test suites pass.

## Exception Translation Requirements For Every Remaining Module

Every remaining module task that adds or changes web and API endpoints must add or update transport-layer tests for failure translation, not just happy-path behavior.

Minimum required assertions per migrated module:

1. Web mutation failures caused by shared-core domain exceptions translate to redirect-plus-flash or equivalent user-visible mutation handling.
2. Web GET-like Inertia failures for page loads, deferred props, partial reloads, or scroll follow-up requests continue using the centralized Inertia exception pipeline rather than ad hoc JSON envelopes.
3. API failures caused by shared-core domain exceptions return structured JSON `4xx` responses under the centralized exception translator.

Reuse and extend:

- `tests/Feature/Architecture/DomainExceptionTranslationTest.php`
- `tests/Feature/Architecture/InertiaExceptionTranslationTest.php`

Do not treat exception translation as implicitly covered by a controller happy-path test.

## Input And Output Contract Rules For Remaining Modules

For every remaining module in this plan:

1. Read pages and read API endpoints must also use injected input Data classes when they accept query params, route-bound models, pagination, filters, or other request-derived context. Do not leave those routes on raw controller `Request` parsing.
2. Web page outputs and API JSON outputs must be represented by separate transport-specific output Data classes. Shared query and use-case actions may return the same domain result objects, but web page props and API payloads must not rely on one shared transport class.

Use this naming convention unless an existing module pattern already establishes a better local variant:

- `app/Data/<Module>/Input/Get...Data.php` for read/page request mapping and authorization
- `app/Data/<Module>/Output/Web/...` for Inertia page or web mutation payload shaping
- `app/Data/<Module>/Output/Api/...` for JSON entity/list payload shaping

---

### Task 1: Accounts Read Model And Web Page Migration

**Files:**

- Modify: `app/Http/Controllers/Ledger/AccountController.php`
- Modify: `app/Http/Controllers/Ledger/DashboardController.php`
- Create: `app/Data/Accounts/Input/GetAccountPageData.php`
- Create: `app/Data/Accounts/Output/Web/AccountData.php`
- Create: `app/Data/Accounts/Output/Web/AccountGroupData.php`
- Create: `app/Data/Accounts/Output/Web/AccountNetWorthData.php`
- Create: `app/Data/Accounts/Output/Web/AccountPageData.php`
- Create: `app/Data/Dashboard/Output/Web/DashboardPageData.php`
- Create: `app/Actions/Accounts/Queries/GetAccountPageQuery.php`
- Create: `app/Actions/Accounts/Queries/ListAccountsByTotalsQuery.php`
- Create: `app/Actions/Accounts/Queries/ListAccountsByTypeQuery.php`
- Create: `app/Actions/Accounts/Queries/GetNetWorthQuery.php`
- Create: `app/Actions/Dashboard/Queries/GetDashboardPageQuery.php`
- Modify: `resources/js/pages/ledgers/accounts/index.tsx`
- Modify: `resources/js/pages/ledgers/accounts/deferred-data.ts`
- Modify: `resources/js/pages/ledgers/accounts/deferred-data.test.ts`
- Test: `tests/Feature/Ledger/AccountTest.php`
- Test: `tests/Feature/Ledger/DashboardPageTest.php`
- Test: `tests/Feature/Architecture/InertiaExceptionTranslationTest.php`

- [ ] **Step 1: Add failing read-contract tests for the accounts page and dashboard reuse**

Extend `tests/Feature/Ledger/AccountTest.php` and `tests/Feature/Ledger/DashboardPageTest.php` so they assert:

- the accounts index still renders `ledgers/accounts/index`
- deferred `accounts`, deferred `accountTypes`, and deferred `netWorth` keep their current prop names unless you intentionally collapse them into one page-level prop and update the page accordingly
- current-balance aggregation still avoids N+1 behavior
- dashboard account cards still group by account type and keep the same balance contract
- at least one GET-like accounts or dashboard failure path is asserted through the centralized Inertia exception pipeline for initial load, deferred props, or partial reload behavior
- dashboard multi-section composition is delivered through one top-level dashboard page query/composer rather than controller-stitched section methods

- [ ] **Step 2: Run only the accounts read tests and confirm failure before implementation**

Run:

```bash
php artisan test --compact tests/Feature/Ledger/AccountTest.php tests/Feature/Ledger/DashboardPageTest.php tests/Feature/Architecture/InertiaExceptionTranslationTest.php
```

Expected: FAIL once the new assertions reference shared-core classes or changed page payload expectations.

- [ ] **Step 3: Implement the accounts output data classes**

Create transport-shaping output classes for:

- a single account row, preserving fields currently emitted by `AccountResource`
- grouped totals payload for the accounts index
- net-worth summary and trend payload
- the top-level accounts page contract

Keep these classes transport-only. Query actions should return plain domain results or collections that the output data wraps.

- [ ] **Step 4: Implement the accounts query layer**

Move the following controller-private logic out of `AccountController`:

- grouping accounts by `include_in_totals`
- grouping accounts by account type for dashboard reuse
- loading current-balance aggregates without N+1 queries
- building net-worth trend/history data

The dashboard should reuse `ListAccountsByTypeQuery` through a top-level `GetDashboardPageQuery` instead of calling `AccountController::groupAccountsByType()` or composing sections inline in `DashboardController`.

- [ ] **Step 5: Refactor the web controller into a thin transport adapter**

`app/Http/Controllers/Ledger/AccountController.php` should:

- keep the existing class path for Wayfinder compatibility
- rely on injected authorized page input data such as `GetAccountPageData` rather than calling `authorize()` inline
- invoke one top-level page query for `index`
- stop owning grouped-account and net-worth computation directly

`app/Http/Controllers/Ledger/DashboardController.php` should also become a thin transport adapter around `GetDashboardPageQuery`, not remain a multi-section composition controller.

- [ ] **Step 6: Update the accounts page only where the new contract requires it**

Preserve:

- pure Inertia browser behavior
- existing deferred behavior and loading states
- existing absolute-value number display rules from `AGENTS.md`

- [ ] **Step 7: Run the accounts read tests until they pass**

Run:

```bash
php artisan test --compact tests/Feature/Ledger/AccountTest.php tests/Feature/Ledger/DashboardPageTest.php tests/Feature/Architecture/InertiaExceptionTranslationTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit the accounts read slice**

```bash
git add app/Http/Controllers/Ledger/AccountController.php app/Http/Controllers/Ledger/DashboardController.php app/Data/Accounts/Output app/Data/Dashboard/Output app/Actions/Accounts/Queries app/Actions/Dashboard/Queries resources/js/pages/ledgers/accounts tests/Feature/Ledger/AccountTest.php tests/Feature/Ledger/DashboardPageTest.php
git commit -m "Extract shared-core account read models for web and dashboard"
```

---

### Task 2: Accounts Writes, Export, And API Surface

**Files:**

- Modify: `app/Http/Controllers/Ledger/AccountController.php`
- Create: `app/Http/Controllers/Api/V1/Ledger/AccountController.php`
- Create: `app/Data/Accounts/Input/StoreAccountData.php`
- Create: `app/Data/Accounts/Input/UpdateAccountData.php`
- Create: `app/Data/Accounts/Input/AdjustAccountBalanceData.php`
- Create: `app/Data/Accounts/Input/ReorderAccountsData.php`
- Create: `app/Data/Accounts/Output/AccountExportRowData.php`
- Create: `app/Actions/Accounts/Queries/ExportAccountTransactionsQuery.php`
- Create: `app/Actions/Accounts/UseCases/StoreAccountAction.php`
- Create: `app/Actions/Accounts/UseCases/UpdateAccountAction.php`
- Create: `app/Actions/Accounts/UseCases/DeleteAccountAction.php`
- Create: `app/Actions/Accounts/UseCases/ReorderAccountsAction.php`
- Create: `app/Actions/Accounts/UseCases/AdjustAccountBalanceAction.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Ledger/AccountCrudTest.php`
- Test: `tests/Feature/Ledger/AccountExportTest.php`
- Test: `tests/Feature/Ledger/AccountTest.php`
- Create: `tests/Feature/Api/V1/Ledger/AccountApiTest.php`
- Test: `tests/Feature/Architecture/DomainExceptionTranslationTest.php`

- [ ] **Step 1: Add failing tests for account writes, export, and API CRUD**

Cover:

- create/update/delete flashes remain `Account created.`, `Account updated.`, and `Account deleted.`
- reorder only updates account ids belonging to the current ledger
- adjust-balance keeps the zero-amount guard and still creates a transaction with the existing success flash `Balance adjusted.`
- export preserves date filters and CSV headers
- API index/store/update/destroy/reorder/adjust-balance return JSON with token auth and ledger scoping
- at least one account domain failure is asserted through the centralized web mutation and API JSON exception translators

- [ ] **Step 2: Run the account write and API tests to verify red**

Run:

```bash
php artisan test --compact tests/Feature/Ledger/AccountCrudTest.php tests/Feature/Ledger/AccountExportTest.php tests/Feature/Ledger/AccountTest.php tests/Feature/Api/V1/Ledger/AccountApiTest.php tests/Feature/Architecture/DomainExceptionTranslationTest.php
```

- [ ] **Step 3: Migrate the account FormRequests into Data input classes**

Port the behavior from:

- `StoreAccountRequest`
- `UpdateAccountRequest`
- `AdjustBalanceRequest`
- existing `ReorderRequest` usage for account reordering

Preserve:

- free-plan account-count gating from `StoreAccountRequest::authorize()`
- custom validation messages and attributes
- ledger-scoped `exists()` rules

- [ ] **Step 4: Implement the accounts use-case layer**

Create one top-level action per endpoint:

- store account
- update account
- delete account
- reorder accounts
- adjust balance by creating the correct ledger transaction row

Keep account writes transport-agnostic. Redirects, flashes, and JSON response codes stay in the controllers and exception translators.

- [ ] **Step 5: Implement export as a query action**

Move CSV row building into `ExportAccountTransactionsQuery` so the controller only streams rows returned by the query action.

- [ ] **Step 6: Add the API controller and routes**

Expose `/api/v1/ledgers/{ledger}/accounts` endpoints with Sanctum token auth, matching the pattern used by Tags, Payees, Categories, and Budgets.

- [ ] **Step 7: Run the account write, export, and API tests until green**

Run:

```bash
php artisan test --compact tests/Feature/Ledger/AccountCrudTest.php tests/Feature/Ledger/AccountExportTest.php tests/Feature/Ledger/AccountTest.php tests/Feature/Api/V1/Ledger/AccountApiTest.php tests/Feature/Architecture/DomainExceptionTranslationTest.php
```

- [ ] **Step 8: Run formatters and commit the accounts module**

Run:

```bash
vendor/bin/pint --dirty --format agent
npm run lint
```

Commit:

```bash
git add app/Data/Accounts app/Actions/Accounts app/Http/Controllers/Ledger/AccountController.php app/Http/Controllers/Api/V1/Ledger/AccountController.php routes/api.php tests/Feature/Ledger/AccountCrudTest.php tests/Feature/Ledger/AccountExportTest.php tests/Feature/Ledger/AccountTest.php tests/Feature/Api/V1/Ledger/AccountApiTest.php
git commit -m "Migrate accounts to shared-core web and API architecture"
```

After this task, what remains to migrate: Bills, Transactions, Import, Reports, optional Phase 7.

---

### Task 3: Bills Read Model, Form Pages, And Dashboard Integration

**Files:**

- Modify: `app/Http/Controllers/Ledger/BillController.php`
- Create: `app/Data/Bills/Input/GetBillIndexPageData.php`
- Create: `app/Data/Bills/Input/GetBillFormPageData.php`
- Create: `app/Data/Bills/Output/Web/BillData.php`
- Create: `app/Data/Bills/Output/Web/BillAccountOptionData.php`
- Create: `app/Data/Bills/Output/Web/BillPageData.php`
- Create: `app/Data/Bills/Output/Web/BillHistoryTransactionData.php`
- Create: `app/Data/Bills/Output/Api/BillData.php`
- Create: `app/Data/Bills/Output/Api/BillListData.php`
- Create: `app/Actions/Bills/Queries/GetBillIndexPageQuery.php`
- Create: `app/Actions/Bills/Queries/GetBillFormPageQuery.php`
- Create: `app/Actions/Bills/Queries/ListBillsQuery.php`
- Create: `app/Actions/Bills/Queries/ListUpcomingBillsQuery.php`
- Create: `app/Actions/Bills/Queries/GetBillAccountOptionsQuery.php`
- Create: `app/Actions/Bills/Queries/GetBillMissedCyclesQuery.php`
- Modify: `app/Http/Controllers/Ledger/DashboardController.php`
- Modify: `resources/js/pages/ledgers/bills/index.tsx`
- Modify: `resources/js/pages/ledgers/bills/create.tsx`
- Modify: `resources/js/pages/ledgers/bills/edit.tsx`
- Test: `tests/Feature/Ledger/BillTest.php`
- Test: `tests/Feature/Ledger/BillIndexAccountAndHistoryTest.php`
- Test: `tests/Feature/Ledger/DashboardPageTest.php`
- Test: `tests/Feature/Architecture/InertiaExceptionTranslationTest.php`

- [ ] **Step 1: Add failing tests around bill page payloads and dashboard upcoming bills**

Preserve current behavior for:

- `ledgers/bills/index`, `ledgers/bills/create`, and `ledgers/bills/edit`
- account options only including visible ledger accounts
- bills index history payload including the latest bill-linked transactions
- dashboard deferred `upcomingBills` groups
- at least one GET-like bills failure path is asserted through the centralized Inertia exception pipeline for initial page, deferred, or partial reload behavior
- read-page authorization and query-param mapping move into injected bill page Data classes instead of raw controller `Request`

- [ ] **Step 2: Run the bill read tests and confirm failure**

Run:

```bash
php artisan test --compact tests/Feature/Ledger/BillTest.php tests/Feature/Ledger/BillIndexAccountAndHistoryTest.php tests/Feature/Ledger/DashboardPageTest.php tests/Feature/Architecture/InertiaExceptionTranslationTest.php
```

- [ ] **Step 3: Implement bill output data classes and query composers**

Move read concerns out of `BillController`:

- bill account options
- categories and payees lists for create/edit pages
- bills listing with related account, category, payee, and recent transactions
- missed-cycle computation for index cards
- dashboard upcoming/due/missed groups

- [ ] **Step 4: Refactor the bill web controller into thin page adapters**

Each action should invoke exactly one top-level page/query composer.

Those page actions should resolve injected `GetBillIndexPageData` or `GetBillFormPageData` rather than raw controller request parsing.

- [ ] **Step 5: Update the bill pages only for contract changes**

Keep:

- pure Inertia forms and navigation
- Wayfinder compatibility with existing browser controller class path
- premium-gated browser routing behavior

- [ ] **Step 6: Update dashboard to consume the bill query layer instead of `BillService` for read-only upcoming data**

Do not migrate auto-processing in this read slice. That belongs to the write slice below.

- [ ] **Step 7: Run the bill read tests until they pass**

Run:

```bash
php artisan test --compact tests/Feature/Ledger/BillTest.php tests/Feature/Ledger/BillIndexAccountAndHistoryTest.php tests/Feature/Ledger/DashboardPageTest.php tests/Feature/Architecture/InertiaExceptionTranslationTest.php
```

- [ ] **Step 8: Commit the bill read slice**

```bash
git add app/Http/Controllers/Ledger/BillController.php app/Http/Controllers/Ledger/DashboardController.php app/Data/Bills/Output app/Actions/Bills/Queries resources/js/pages/ledgers/bills tests/Feature/Ledger/BillTest.php tests/Feature/Ledger/BillIndexAccountAndHistoryTest.php tests/Feature/Ledger/DashboardPageTest.php
git commit -m "Extract shared-core bill read models for web and dashboard"
```

---

### Task 4: Bills Writes, Auto-Processing, And API Surface

**Files:**

- Modify: `app/Http/Controllers/Ledger/BillController.php`
- Create: `app/Http/Controllers/Api/V1/Ledger/BillController.php`
- Create: `app/Data/Bills/Input/StoreBillData.php`
- Create: `app/Data/Bills/Input/UpdateBillData.php`
- Create: `app/Data/Bills/Input/PayBillData.php`
- Create: `app/Actions/Bills/UseCases/StoreBillAction.php`
- Create: `app/Actions/Bills/UseCases/UpdateBillAction.php`
- Create: `app/Actions/Bills/UseCases/DeleteBillAction.php`
- Create: `app/Actions/Bills/UseCases/ToggleBillAction.php`
- Create: `app/Actions/Bills/UseCases/PayBillAction.php`
- Create: `app/Actions/Bills/UseCases/ProcessAutoBillsAction.php`
- Modify or delete: `app/Services/BillService.php`
- Modify: `routes/console.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Ledger/BillCrudTest.php`
- Test: `tests/Feature/Ledger/BillServiceTest.php`
- Test: `tests/Feature/Ledger/BillTest.php`
- Test: `tests/Feature/Ledger/ExportTest.php`
- Create: `tests/Feature/Api/V1/Ledger/BillApiTest.php`
- Test: `tests/Feature/Architecture/DomainExceptionTranslationTest.php`

- [ ] **Step 1: Add failing tests for bill mutations, scheduled processing, and API endpoints**

Preserve:

- flashes `Recurring transaction created.`, `Recurring transaction updated.`, `Recurring transaction deleted.`
- toggle flashes `Recurring transaction activated.` and `Recurring transaction deactivated.`
- `pay()` flash `{$bill->name} marked as paid.`
- inline payee creation behavior for `new_payee_name`
- transfer-bill pay behavior creating paired transactions
- auto-processing command scheduler still using a single shared top-level action
- at least one bill domain failure path is asserted through the centralized web mutation and API JSON exception translators

- [ ] **Step 2: Run the bill mutation, service, and API tests to verify red**

Run:

```bash
php artisan test --compact tests/Feature/Ledger/BillCrudTest.php tests/Feature/Ledger/BillServiceTest.php tests/Feature/Ledger/BillTest.php tests/Feature/Ledger/ExportTest.php tests/Feature/Api/V1/Ledger/BillApiTest.php tests/Feature/Architecture/DomainExceptionTranslationTest.php
```

- [ ] **Step 3: Migrate the bill FormRequests into Data input classes**

Port:

- `StoreBillRequest`
- `UpdateBillRequest`
- `PayBillRequest`

Preserve all custom messages and transfer-specific validation rules.

- [ ] **Step 4: Implement top-level bill use cases**

Create one use-case action per endpoint and one scheduled action for auto-processing. The pay action should own:

- transfer-vs-non-transfer branching
- due-date advancement
- occurrence counting
- automatic deactivation at bill end conditions

- [ ] **Step 5: Replace `BillService` call sites**

Move callers in:

- `BillController`
- `DashboardController` read path if any write-only methods still leak through
- `routes/console.php`

Delete `BillService` only after all tests referencing it are updated to target the new action classes or the service is reduced to a temporary adapter with no unique logic left.

- [ ] **Step 6: Add API routes and controller for bills**

Keep premium middleware for bills on the API surface as well.

- [ ] **Step 7: Run the bill write and API suites until green**

Run:

```bash
php artisan test --compact tests/Feature/Ledger/BillCrudTest.php tests/Feature/Ledger/BillServiceTest.php tests/Feature/Ledger/BillTest.php tests/Feature/Ledger/ExportTest.php tests/Feature/Api/V1/Ledger/BillApiTest.php tests/Feature/Architecture/DomainExceptionTranslationTest.php
```

- [ ] **Step 8: Run formatters and commit the bills module**

Run:

```bash
vendor/bin/pint --dirty --format agent
npm run lint
```

Commit:

```bash
git add app/Data/Bills app/Actions/Bills app/Http/Controllers/Ledger/BillController.php app/Http/Controllers/Api/V1/Ledger/BillController.php app/Http/Controllers/Ledger/DashboardController.php app/Services/BillService.php routes/api.php routes/console.php tests/Feature/Ledger/BillCrudTest.php tests/Feature/Ledger/BillServiceTest.php tests/Feature/Ledger/BillTest.php tests/Feature/Ledger/ExportTest.php tests/Feature/Api/V1/Ledger/BillApiTest.php
git commit -m "Migrate bills to shared-core web and API architecture"
```

After this task, what remains to migrate: Transactions, Import, Reports, optional Phase 7.

---

### Task 5: Transactions Read Model, Filters, And Edit Page Migration

**Files:**

- Modify: `app/Http/Controllers/Ledger/TransactionController.php`
- Create: `app/Data/Transactions/Input/GetTransactionIndexData.php`
- Create: `app/Data/Transactions/Output/Web/TransactionData.php`
- Create: `app/Data/Transactions/Output/Web/TransactionIndexPageData.php`
- Create: `app/Data/Transactions/Output/Web/TransactionEditPageData.php`
- Create: `app/Data/Transactions/Output/Web/TransactionFiltersData.php`
- Create: `app/Data/Transactions/Output/Api/TransactionData.php`
- Create: `app/Data/Transactions/Output/Api/TransactionListData.php`
- Create: `app/Actions/Transactions/Queries/GetTransactionIndexPageQuery.php`
- Create: `app/Actions/Transactions/Queries/GetTransactionEditPageQuery.php`
- Create: `app/Actions/Transactions/Queries/ListTransactionsQuery.php`
- Create: `app/Actions/Transactions/Queries/ListTransactionAccountsQuery.php`
- Create: `app/Actions/Transactions/Queries/ListTransactionCategoriesQuery.php`
- Create: `app/Actions/Transactions/Queries/ListTransactionPayeesQuery.php`
- Create: `app/Actions/Transactions/Queries/ListTransactionTagsQuery.php`
- Create: `app/Actions/Transactions/Queries/NormalizeTransactionFiltersQuery.php`
- Create: `app/Actions/Transactions/Queries/LoadTransferPairRelationsQuery.php`
- Modify: `resources/js/pages/ledgers/transactions/index.tsx`
- Modify: `resources/js/pages/ledgers/transactions/edit.tsx`
- Modify: `resources/js/pages/ledgers/transactions/query-params.ts`
- Modify: `resources/js/pages/ledgers/transactions/query-params.test.ts`
- Modify: `resources/js/pages/ledgers/transactions/mobile-transaction-row-data.ts`
- Modify: `resources/js/pages/ledgers/transactions/mobile-transaction-row-data.test.ts`
- Modify: `resources/js/pages/ledgers/transactions/mobile-transaction-groups.ts`
- Modify: `resources/js/pages/ledgers/transactions/mobile-transaction-groups.test.ts`
- Modify: `resources/js/pages/ledgers/transactions/mobile-transaction-list.tsx`
- Test: `tests/Feature/Ledger/TransactionPageTest.php`
- Test: `tests/Feature/Ledger/TransactionSearchTest.php`
- Test: `tests/Feature/Ledger/TransactionTest.php`
- Test: `tests/Feature/Architecture/InertiaExceptionTranslationTest.php`

- [ ] **Step 1: Add failing tests for transaction page payloads, filter normalization, and transfer-pair hydration**

Cover:

- Inertia scroll/deferred transactions payload still works
- account/category/payee/tag filter options remain ledger-scoped and ordered as before
- edit page still loads transfer-pair data, tags, splits, and attachments
- search/filter parsing keeps support for both `key[]` and comma-separated input forms
- at least one GET-like transaction page failure is asserted through the centralized Inertia exception pipeline for initial page, deferred follow-up, or partial reload behavior
- query-string mapping for list filters and pagination is owned by injected transaction input Data rather than raw controller request parsing

- [ ] **Step 2: Run the transaction read tests and confirm red**

Run:

```bash
php artisan test --compact tests/Feature/Ledger/TransactionPageTest.php tests/Feature/Ledger/TransactionSearchTest.php tests/Feature/Ledger/TransactionTest.php tests/Feature/Architecture/InertiaExceptionTranslationTest.php
```

- [ ] **Step 3: Implement transaction output data classes**

Replace `TransactionResource` as the primary shaping contract for migrated surfaces while preserving the existing field names the frontend depends on.

- [ ] **Step 4: Extract filter normalization and list queries**

Move out of the controller:

- injected query-param mapping through `GetTransactionIndexData`
- filter normalization
- array-filter parsing
- main transaction listing with `simplePaginate`
- transfer-pair normalization across visible and hidden counterpart rows
- account/category/payee/tag option loading

- [ ] **Step 5: Refactor transaction index and edit actions into one top-level page query each**

`index()` should not build query composition inline anymore.

`edit()` should not manually load the page payload inline anymore.

- [ ] **Step 6: Update frontend helpers for the new contract**

Keep current mobile grouping and query-param semantics intact unless tests show the contract must change.

- [ ] **Step 7: Run the transaction read tests until green**

Run:

```bash
php artisan test --compact tests/Feature/Ledger/TransactionPageTest.php tests/Feature/Ledger/TransactionSearchTest.php tests/Feature/Ledger/TransactionTest.php tests/Feature/Architecture/InertiaExceptionTranslationTest.php
```

- [ ] **Step 8: Commit the transaction read slice**

```bash
git add app/Http/Controllers/Ledger/TransactionController.php app/Data/Transactions/Output app/Actions/Transactions/Queries resources/js/pages/ledgers/transactions tests/Feature/Ledger/TransactionPageTest.php tests/Feature/Ledger/TransactionSearchTest.php tests/Feature/Ledger/TransactionTest.php
git commit -m "Extract shared-core transaction read models for web pages"
```

---

### Task 6: Transactions Writes, Splits, Transfers, Attachments, And Budget Side Effects

**Files:**

- Modify: `app/Http/Controllers/Ledger/TransactionController.php`
- Create: `app/Http/Controllers/Api/V1/Ledger/TransactionController.php`
- Create: `app/Http/Controllers/Api/V1/Ledger/TransactionAttachmentController.php`
- Create: `app/Data/Transactions/Input/StoreTransactionData.php`
- Create: `app/Data/Transactions/Input/UpdateTransactionData.php`
- Create: `app/Data/Transactions/Input/StoreTransactionAttachmentsData.php`
- Create: `app/Data/Transactions/Input/DeleteTransactionAttachmentData.php`
- Create: `app/Actions/Transactions/UseCases/StoreTransactionAction.php`
- Create: `app/Actions/Transactions/UseCases/UpdateTransactionAction.php`
- Create: `app/Actions/Transactions/UseCases/DeleteTransactionAction.php`
- Create: `app/Actions/Transactions/UseCases/StoreTransactionAttachmentsAction.php`
- Create: `app/Actions/Transactions/UseCases/DeleteTransactionAttachmentAction.php`
- Create: `app/Actions/Transactions/UseCases/CelebrateFirstTransactionAction.php`
- Modify or delete: `app/Services/TransactionService.php`
- Modify: `app/Http/Controllers/Ledger/AttachmentController.php`
- Modify: `routes/api.php`
- Delete: `app/Http/Requests/StoreAttachmentRequest.php` once the controller fully resolves Data input instead
- Test: `tests/Feature/Ledger/TransactionCrudTest.php`
- Test: `tests/Feature/Ledger/TransactionAmountGuardrailTest.php`
- Test: `tests/Feature/Ledger/TransactionTypeConversionTest.php`
- Test: `tests/Feature/Ledger/SplitTransactionTest.php`
- Test: `tests/Feature/Ledger/AttachmentTest.php`
- Test: `tests/Feature/Ledger/TransferAttachmentTest.php`
- Test: `tests/Feature/Ledger/TransactionServiceTest.php`
- Test: `tests/Feature/Ledger/TransactionSplitServiceTest.php`
- Test: `tests/Feature/Ledger/TransactionTest.php`
- Test: `tests/Feature/Architecture/DomainExceptionTranslationTest.php`
- Test: `tests/Feature/Api/V1/Ledger/TransactionApiTest.php`

- [ ] **Step 1: Add failing tests for transaction write orchestration and side effects**

Preserve:

- inline payee creation on create/update
- transfer create/update/delete behavior
- split validation and split syncing behavior
- attachment storage behavior
- standalone attachment upload, listing, download, and delete behavior from `AttachmentController`
- transfer attachment behavior currently covered by `tests/Feature/Ledger/TransferAttachmentTest.php`
- split-specific page and write behavior currently covered by `tests/Feature/Ledger/SplitTransactionTest.php`
- first-transaction celebration flash behavior
- budget threshold checks after relevant writes
- existing redirect behavior for create/update/delete
- at least one transaction domain failure path is asserted through the centralized web mutation and API JSON exception translators
- non-browser API write coverage includes attachment upload, listing, and delete flows, not just core transaction rows

- [ ] **Step 2: Run the transaction write tests to verify red**

Run:

```bash
php artisan test --compact tests/Feature/Ledger/TransactionCrudTest.php tests/Feature/Ledger/TransactionAmountGuardrailTest.php tests/Feature/Ledger/TransactionTypeConversionTest.php tests/Feature/Ledger/SplitTransactionTest.php tests/Feature/Ledger/AttachmentTest.php tests/Feature/Ledger/TransferAttachmentTest.php tests/Feature/Ledger/TransactionServiceTest.php tests/Feature/Ledger/TransactionSplitServiceTest.php tests/Feature/Ledger/TransactionTest.php tests/Feature/Api/V1/Ledger/TransactionApiTest.php tests/Feature/Architecture/DomainExceptionTranslationTest.php
```

- [ ] **Step 3: Port the transaction FormRequests into Data input classes**

Migrate:

- `StoreTransactionRequest`
- `UpdateTransactionRequest`
- attachment upload and delete request handling into `StoreTransactionAttachmentsData` and `DeleteTransactionAttachmentData`

Preserve:

- free-plan account restrictions on create
- split total validation
- attachment validation
- all existing custom validation strings

- [ ] **Step 4: Implement top-level transaction write actions**

Split by endpoint, not by tiny helper methods. Internal helpers are fine, but controllers should call one top-level action for store, update, and destroy.

Attachment upload and delete must also use top-level actions so the standalone attachment transport is migrated out of `FormRequest` plus inline controller storage logic.

- [ ] **Step 5: Replace `TransactionService` call sites carefully**

Move controller callers first. Only then decide whether `TransactionService` can be deleted or whether a minimal adapter remains temporarily for Import and Bills until their tasks complete.

This slice must also add or expand the transaction API write surface for attachment upload, list, and delete, either through `Api\V1\Ledger\TransactionAttachmentController` or an equivalently explicit API transport adapter. Do not leave attachment workflows unspecified once transaction writes are declared migrated.

- [ ] **Step 6: Add the transaction API write controllers and routes for the migrated write surface**

Before the bulk/export-heavy API slice in Task 7, add the write-side API transport for:

- store
- update
- destroy
- attachment list
- attachment upload
- attachment delete

- [ ] **Step 7: Run the transaction write suites until green**

Run:

```bash
php artisan test --compact tests/Feature/Ledger/TransactionCrudTest.php tests/Feature/Ledger/TransactionAmountGuardrailTest.php tests/Feature/Ledger/TransactionTypeConversionTest.php tests/Feature/Ledger/SplitTransactionTest.php tests/Feature/Ledger/AttachmentTest.php tests/Feature/Ledger/TransferAttachmentTest.php tests/Feature/Ledger/TransactionServiceTest.php tests/Feature/Ledger/TransactionSplitServiceTest.php tests/Feature/Ledger/TransactionTest.php tests/Feature/Api/V1/Ledger/TransactionApiTest.php tests/Feature/Architecture/DomainExceptionTranslationTest.php
```

- [ ] **Step 8: Commit the transaction write slice**

```bash
git add app/Http/Controllers/Ledger/TransactionController.php app/Http/Controllers/Ledger/AttachmentController.php app/Http/Controllers/Api/V1/Ledger/TransactionController.php app/Http/Controllers/Api/V1/Ledger/TransactionAttachmentController.php app/Data/Transactions/Input app/Actions/Transactions/UseCases app/Services/TransactionService.php routes/api.php tests/Feature/Ledger/TransactionCrudTest.php tests/Feature/Ledger/TransactionAmountGuardrailTest.php tests/Feature/Ledger/TransactionTypeConversionTest.php tests/Feature/Ledger/SplitTransactionTest.php tests/Feature/Ledger/AttachmentTest.php tests/Feature/Ledger/TransferAttachmentTest.php tests/Feature/Ledger/TransactionServiceTest.php tests/Feature/Ledger/TransactionSplitServiceTest.php tests/Feature/Ledger/TransactionTest.php tests/Feature/Api/V1/Ledger/TransactionApiTest.php
git commit -m "Move transaction write workflows into shared-core actions"
```

---

### Task 7: Transactions Bulk Actions, Export, Select-All, And API Surface

**Files:**

- Modify: `app/Http/Controllers/Ledger/TransactionController.php`
- Create: `app/Http/Controllers/Api/V1/Ledger/TransactionController.php`
- Create: `app/Http/Controllers/Api/V1/Ledger/TransactionAttachmentController.php`
- Create: `app/Data/Transactions/Input/GetTransactionIndexData.php`
- Create: `app/Data/Transactions/Input/BulkUpdateTransactionsData.php`
- Create: `app/Data/Transactions/Input/BulkDestroyTransactionsData.php`
- Create: `app/Data/Transactions/Input/SelectAllTransactionsData.php`
- Create: `app/Data/Transactions/Input/ExportTransactionsData.php`
- Create: `app/Data/Transactions/Output/TransactionExportRowData.php`
- Create: `app/Actions/Transactions/Queries/SelectAllTransactionIdsQuery.php`
- Create: `app/Actions/Transactions/Queries/ExportTransactionsQuery.php`
- Create: `app/Actions/Transactions/UseCases/BulkUpdateTransactionsAction.php`
- Create: `app/Actions/Transactions/UseCases/BulkDestroyTransactionsAction.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Ledger/TransactionBulkActionTest.php`
- Test: `tests/Feature/Ledger/TransactionSearchTest.php`
- Test: `tests/Feature/Ledger/TransactionTest.php`
- Create: `tests/Feature/Api/V1/Ledger/TransactionApiTest.php`
- Test: `tests/Feature/Architecture/DomainExceptionTranslationTest.php`
- Test: `tests/Feature/Architecture/InertiaExceptionTranslationTest.php`

- [ ] **Step 1: Add failing tests for bulk update, bulk destroy, export, select-all, and API CRUD/listing**

Include explicit coverage that:

- bulk actions respect `apply_to_all_matching` and `excluded_ids`
- transfer pairs are deleted together
- export respects the same filter semantics as the web page
- API list honors token auth and filter contracts
- at least one GET-like transaction failure is asserted through the centralized Inertia exception pipeline for deferred, scroll, or partial reload requests
- at least one transaction API domain failure is asserted as structured JSON `4xx`

- [ ] **Step 2: Run the bulk, export, and API tests to confirm red**

Run:

```bash
php artisan test --compact tests/Feature/Ledger/TransactionBulkActionTest.php tests/Feature/Ledger/TransactionSearchTest.php tests/Feature/Ledger/TransactionTest.php tests/Feature/Api/V1/Ledger/TransactionApiTest.php tests/Feature/Architecture/DomainExceptionTranslationTest.php tests/Feature/Architecture/InertiaExceptionTranslationTest.php
```

- [ ] **Step 3: Port the bulk FormRequests into Data input classes**

Migrate:

- `GetTransactionIndexData` for shared web/API list filter mapping
- `BulkUpdateTransactionsRequest`
- `BulkDestroyTransactionsRequest`

Preserve custom validation messages and ledger-scoped target validation.

- [ ] **Step 4: Implement the bulk and export actions/queries**

The bulk actions should reuse the normalized filter/query layer from Task 5 instead of duplicating filter parsing.

- [ ] **Step 5: Add the transaction API controller and routes**

Expose:

- index
- show/edit-equivalent payload only if needed by first-party clients
- store
- update
- destroy
- bulk update
- bulk destroy
- select-all ids
- attachment list
- attachment upload
- attachment delete

- [ ] **Step 6: Run the bulk, export, and API tests until green**

Run:

```bash
php artisan test --compact tests/Feature/Ledger/TransactionBulkActionTest.php tests/Feature/Ledger/TransactionSearchTest.php tests/Feature/Ledger/TransactionTest.php tests/Feature/Api/V1/Ledger/TransactionApiTest.php tests/Feature/Architecture/DomainExceptionTranslationTest.php tests/Feature/Architecture/InertiaExceptionTranslationTest.php
```

- [ ] **Step 7: Run formatters and commit the transactions module**

Run:

```bash
vendor/bin/pint --dirty --format agent
npm run lint
```

Commit:

```bash
git add app/Data/Transactions app/Actions/Transactions app/Http/Controllers/Ledger/TransactionController.php app/Http/Controllers/Api/V1/Ledger/TransactionController.php routes/api.php tests/Feature/Ledger/TransactionBulkActionTest.php tests/Feature/Ledger/TransactionSearchTest.php tests/Feature/Ledger/TransactionTest.php tests/Feature/Api/V1/Ledger/TransactionApiTest.php
git commit -m "Migrate transactions to shared-core web and API architecture"
```

After this task, what remains to migrate: Import, Reports, optional Phase 7.

---

### Task 8: Import Parse Flow And Web Page Migration

**Files:**

- Modify: `app/Http/Controllers/Ledger/ImportController.php`
- Create: `app/Data/Imports/Input/GetImportPageData.php`
- Create: `app/Data/Imports/Input/ParseImportData.php`
- Create: `app/Data/Imports/Output/PendingImportHandleData.php`
- Create: `app/Data/Imports/Output/Web/ImportParseResultData.php`
- Create: `app/Data/Imports/Output/Web/ImportHistoryData.php`
- Create: `app/Data/Imports/Output/Web/ImportMappingData.php`
- Create: `app/Data/Imports/Output/Web/ImportPageData.php`
- Create: `app/Data/Imports/Output/Api/ImportParseResultData.php`
- Create: `app/Data/Imports/Output/Api/ImportExecutionData.php`
- Create: `app/Actions/Imports/Queries/GetImportPageQuery.php`
- Create: `app/Actions/Imports/Queries/DetectImportBankFormatQuery.php`
- Create: `app/Actions/Imports/Queries/ParseImportPreviewQuery.php`
- Create: `app/Actions/Imports/UseCases/ParseImportAction.php`
- Create: `app/Actions/Imports/UseCases/CreatePendingImportHandleAction.php`
- Modify: `resources/js/pages/ledgers/import/index.tsx`
- Test: `tests/Feature/Ledger/ImportTest.php`
- Test: `tests/Feature/Architecture/InertiaExceptionTranslationTest.php`

- [ ] **Step 1: Add failing tests for import page payloads and parse-preview behavior**

Preserve:

- page component `ledgers/import/index`
- deferred `accounts`, `savedMappings`, and `importHistory`
- parse preview session flash payload shape
- detected bank and suggested mapping behavior
- configured ledger storage disk behavior
- any GET-like import page failure continues through the centralized Inertia exception pipeline rather than ad hoc JSON

- [ ] **Step 2: Run the import page and parse tests to confirm red**

Run:

```bash
php artisan test --compact tests/Feature/Ledger/ImportTest.php tests/Feature/Architecture/InertiaExceptionTranslationTest.php
```

- [ ] **Step 3: Port `ParseImportRequest` into `ParseImportData`**

Preserve the custom validation messages around missing/invalid CSV uploads.

- [ ] **Step 4: Implement import page and parse query/use-case classes**

Move out of `ImportController`:

- page data loading
- CSV preview parsing
- temporary file storage and pending-handle creation
- bank-format detection and suggested mapping generation

Keep browser session key assignment at the transport edge. Shared-core parse actions may return a pending import handle or temp-file reference result, but the web controller or web output mapping must remain responsible for storing any browser-session state used by the Inertia flow.

- [ ] **Step 5: Refactor the web controller into thin page and parse adapters**

Keep browser behavior pure Inertia. Parse should continue redirecting back to the import page with session state, not return ad hoc JSON to the browser.

- [ ] **Step 6: Run the import page and parse tests until green**

Run:

```bash
php artisan test --compact tests/Feature/Ledger/ImportTest.php tests/Feature/Architecture/InertiaExceptionTranslationTest.php
```

- [ ] **Step 7: Commit the import parse slice**

```bash
git add app/Http/Controllers/Ledger/ImportController.php app/Data/Imports/Output app/Data/Imports/Input/ParseImportData.php app/Actions/Imports/Queries app/Actions/Imports/UseCases/ParseImportAction.php resources/js/pages/ledgers/import/index.tsx tests/Feature/Ledger/ImportTest.php
git commit -m "Extract shared-core import page and parse flow"
```

---

### Task 9: Import Execute, Mapping Management, And API Surface

**Files:**

- Modify: `app/Http/Controllers/Ledger/ImportController.php`
- Create: `app/Http/Controllers/Api/V1/Ledger/ImportController.php`
- Create: `app/Data/Imports/Input/StoreImportData.php`
- Create: `app/Data/Imports/Input/StoreImportMappingData.php`
- Create: `app/Data/Imports/Output/PendingImportHandleData.php`
- Create: `app/Actions/Imports/UseCases/ExecuteImportAction.php`
- Create: `app/Actions/Imports/UseCases/CreatePendingImportHandleAction.php`
- Create: `app/Actions/Imports/UseCases/StoreImportMappingAction.php`
- Create: `app/Actions/Imports/UseCases/DeleteImportMappingAction.php`
- Create: `app/Actions/Imports/UseCases/ResolvePendingImportHandleAction.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Ledger/ImportTest.php`
- Test: `tests/Feature/Ledger/ImportMappingTest.php`
- Create: `tests/Feature/Api/V1/Ledger/ImportApiTest.php`
- Test: `tests/Feature/Architecture/DomainExceptionTranslationTest.php`

- [ ] **Step 1: Add failing tests for import execution, mapping CRUD, and API endpoints**

Cover:

- web-session-pinned pending import safety for browser flows
- duplicate skipping semantics
- payee/category resolution during import
- import history creation and temp-file cleanup
- saved mapping create/delete behavior
- API parse/execute/mapping endpoints for non-browser first-party clients
- API parse/execute uses a transport-agnostic opaque pending import handle instead of browser-session-only `file_path` ownership
- at least one web mutation failure for import execute or mapping CRUD is asserted through the centralized redirect-plus-flash exception translator
- at least one import API domain failure is asserted as structured JSON `4xx`

- [ ] **Step 2: Run the import execute, mapping, and API tests to confirm red**

Run:

```bash
php artisan test --compact tests/Feature/Ledger/ImportTest.php tests/Feature/Ledger/ImportMappingTest.php tests/Feature/Api/V1/Ledger/ImportApiTest.php tests/Feature/Architecture/DomainExceptionTranslationTest.php
```

- [ ] **Step 3: Port the remaining import FormRequests into Data input classes**

Migrate:

- `StoreImportRequest`
- `StoreImportMappingRequest`

For the web surface, preserve the current guarantee that execute can only consume the file most recently parsed by that browser session.

For the API surface, replace the raw `file_path` session check with an opaque pending import handle issued during parse and resolved during execute. The handle may internally map to the same temp file, but API clients must not depend on browser session state or raw storage paths.

- [ ] **Step 4: Implement the import execution use case on top of migrated transaction writes**

`ExecuteImportAction` must call the new transaction use-case/core, not reintroduce direct controller-level orchestration or revive the old `TransactionService` contract.

- [ ] **Step 5: Add the API controller and routes for import workflows**

This surface is for non-browser first-party clients. The web page must remain on browser routes. API parse should return a pending import handle that API execute submits later instead of leaking raw temporary storage paths as the client contract.

- [ ] **Step 6: Run the import execute, mapping, and API tests until green**

Run:

```bash
php artisan test --compact tests/Feature/Ledger/ImportTest.php tests/Feature/Ledger/ImportMappingTest.php tests/Feature/Api/V1/Ledger/ImportApiTest.php tests/Feature/Architecture/DomainExceptionTranslationTest.php
```

- [ ] **Step 7: Run formatters and commit the import module**

Run:

```bash
vendor/bin/pint --dirty --format agent
npm run lint
```

Commit:

```bash
git add app/Data/Imports app/Actions/Imports app/Http/Controllers/Ledger/ImportController.php app/Http/Controllers/Api/V1/Ledger/ImportController.php routes/api.php tests/Feature/Ledger/ImportTest.php tests/Feature/Ledger/ImportMappingTest.php tests/Feature/Api/V1/Ledger/ImportApiTest.php
git commit -m "Migrate import workflows to shared-core web and API architecture"
```

After this task, what remains to migrate: Reports, optional Phase 7.

---

### Task 10: Reports Spending, Comparison, And PDF Query Migration

**Files:**

- Modify: `app/Http/Controllers/Ledger/ReportController.php`
- Create: `app/Data/Reports/Input/ReportFiltersData.php`
- Create: `app/Data/Reports/Input/ExportReportPdfData.php`
- Create: `app/Data/Reports/Output/Web/SpendingReportData.php`
- Create: `app/Data/Reports/Output/Web/ReportDateRangeData.php`
- Create: `app/Data/Reports/Output/Api/SpendingReportData.php`
- Create: `app/Actions/Reports/Queries/GetSpendingReportPageQuery.php`
- Create: `app/Actions/Reports/Queries/GetSpendingReportDataQuery.php`
- Create: `app/Actions/Reports/Queries/GetReportComparisonQuery.php`
- Create: `app/Actions/Reports/Queries/GetReportPdfPayloadQuery.php`
- Modify: `resources/js/pages/ledgers/reports/index.tsx`
- Modify: `resources/views/reports/monthly-pdf.blade.php`
- Modify or delete: `app/Services/ReportService.php`
- Test: `tests/Feature/Ledger/ReportTest.php`
- Test: `tests/Feature/Ledger/ReportComparisonTest.php`
- Test: `tests/Feature/Ledger/ReportPdfExportTest.php`
- Test: `tests/Feature/Architecture/InertiaExceptionTranslationTest.php`

- [ ] **Step 1: Add failing tests for the spending report page, comparison payload, and PDF export payload**

Preserve:

- page component `ledgers/reports/index`
- default cycle-aware date range and preset detection
- comparison payload shape
- PDF export file naming and rendered summary totals
- GET-like report failures, including deferred follow-ups, continue through the centralized Inertia exception pipeline

- [ ] **Step 2: Run the spending, comparison, and PDF tests to confirm red**

Run:

```bash
php artisan test --compact tests/Feature/Ledger/ReportTest.php tests/Feature/Ledger/ReportComparisonTest.php tests/Feature/Ledger/ReportPdfExportTest.php tests/Feature/Architecture/InertiaExceptionTranslationTest.php
```

- [ ] **Step 3: Port report filters into Data input classes**

Controllers should no longer hand-build filter arrays inline.

- [ ] **Step 4: Extract report page/query classes from `ReportService`**

Move out:

- spending report composition
- comparison building
- PDF payload building
- date-range/preset detection used by the report page

- [ ] **Step 5: Refactor the spending report web controller actions**

Each report route should call one top-level page/query action.

- [ ] **Step 6: Run the report spending/comparison/PDF tests until green**

Run:

```bash
php artisan test --compact tests/Feature/Ledger/ReportTest.php tests/Feature/Ledger/ReportComparisonTest.php tests/Feature/Ledger/ReportPdfExportTest.php tests/Feature/Architecture/InertiaExceptionTranslationTest.php
```

- [ ] **Step 7: Commit the reports spending slice**

```bash
git add app/Http/Controllers/Ledger/ReportController.php app/Data/Reports/Input/ReportFiltersData.php app/Data/Reports/Input/ExportReportPdfData.php app/Data/Reports/Output/Web/SpendingReportData.php app/Data/Reports/Output/Web/ReportDateRangeData.php app/Data/Reports/Output/Api/SpendingReportData.php app/Actions/Reports/Queries resources/js/pages/ledgers/reports/index.tsx resources/views/reports/monthly-pdf.blade.php app/Services/ReportService.php tests/Feature/Ledger/ReportTest.php tests/Feature/Ledger/ReportComparisonTest.php tests/Feature/Ledger/ReportPdfExportTest.php
git commit -m "Extract shared-core spending and PDF report queries"
```

---

### Task 11: Reports Financial Health, Cash Flow, Budget Performance, And API Surface

**Files:**

- Modify: `app/Http/Controllers/Ledger/ReportController.php`
- Create: `app/Http/Controllers/Api/V1/Ledger/ReportController.php`
- Create: `app/Data/Reports/Input/GetFinancialHealthPageData.php`
- Create: `app/Data/Reports/Input/GetBudgetPerformancePageData.php`
- Create: `app/Data/Reports/Input/GetCashFlowPageData.php`
- Create: `app/Data/Reports/Input/BudgetPerformanceFiltersData.php`
- Create: `app/Data/Reports/Output/Web/FinancialHealthReportData.php`
- Create: `app/Data/Reports/Output/Web/BudgetPerformanceReportData.php`
- Create: `app/Data/Reports/Output/Web/CashFlowReportData.php`
- Create: `app/Data/Reports/Output/Api/FinancialHealthReportData.php`
- Create: `app/Data/Reports/Output/Api/BudgetPerformanceReportData.php`
- Create: `app/Data/Reports/Output/Api/CashFlowReportData.php`
- Create: `app/Actions/Reports/Queries/GetFinancialHealthPageQuery.php`
- Create: `app/Actions/Reports/Queries/GetBudgetPerformancePageQuery.php`
- Create: `app/Actions/Reports/Queries/GetCashFlowPageQuery.php`
- Create: `app/Actions/Reports/Queries/GetFinancialHealthReportDataQuery.php`
- Create: `app/Actions/Reports/Queries/GetBudgetPerformanceReportDataQuery.php`
- Create: `app/Actions/Reports/Queries/GetCashFlowReportDataQuery.php`
- Modify or delete: `app/Services/ReportService.php`
- Modify: `routes/api.php`
- Modify: `resources/js/pages/ledgers/reports/financial-health.tsx`
- Modify: `resources/js/pages/ledgers/reports/budget-performance.tsx`
- Modify: `resources/js/pages/ledgers/reports/cash-flow.tsx`
- Test: `tests/Feature/Ledger/ReportTest.php`
- Create: `tests/Feature/Api/V1/Ledger/ReportApiTest.php`
- Test: `tests/Feature/Architecture/DomainExceptionTranslationTest.php`
- Test: `tests/Feature/Architecture/InertiaExceptionTranslationTest.php`

- [ ] **Step 1: Add failing tests for the remaining report pages and API endpoints**

Cover:

- `financial-health`, `budget-performance`, and `cash-flow` page payloads
- budget-performance payload shape preserved from the recent Budget migration
- cash-flow upcoming bills payload still works after the Bills migration
- API read endpoints return JSON under token auth
- GET-like failures for these report pages are asserted through the centralized Inertia exception pipeline
- report API routes preserve the current premium gating already enforced on the web surface
- at least one report API domain failure is asserted as structured JSON `4xx`

- [ ] **Step 2: Run the remaining report tests and confirm red**

Run:

```bash
php artisan test --compact tests/Feature/Ledger/ReportTest.php tests/Feature/Api/V1/Ledger/ReportApiTest.php tests/Feature/Architecture/DomainExceptionTranslationTest.php tests/Feature/Architecture/InertiaExceptionTranslationTest.php
```

- [ ] **Step 3: Extract the remaining report read logic into dedicated queries**

Move out:

- financial health history and snapshot computation
- budget performance composition using the migrated Budget queries
- cash-flow daily trend and upcoming-bill composition using the migrated Bill queries

- [ ] **Step 4: Add the report API controller and routes**

Expose first-party JSON reads for the four report surfaces and preserve premium middleware on the API routes just as the web report routes already require it.

- [ ] **Step 5: Remove or reduce `ReportService`**

Delete it if no meaningful shared logic remains. Otherwise reduce it to a temporary adapter with no controller-only composition left inside.

- [ ] **Step 6: Run the remaining report suites until green**

Run:

```bash
php artisan test --compact tests/Feature/Ledger/ReportTest.php tests/Feature/Api/V1/Ledger/ReportApiTest.php tests/Feature/Architecture/DomainExceptionTranslationTest.php tests/Feature/Architecture/InertiaExceptionTranslationTest.php
```

- [ ] **Step 7: Run formatters and commit the reports module**

Run:

```bash
vendor/bin/pint --dirty --format agent
npm run lint
```

Commit:

```bash
git add app/Data/Reports app/Actions/Reports app/Http/Controllers/Ledger/ReportController.php app/Http/Controllers/Api/V1/Ledger/ReportController.php app/Services/ReportService.php routes/api.php resources/js/pages/ledgers/reports tests/Feature/Ledger/ReportTest.php tests/Feature/Ledger/ReportComparisonTest.php tests/Feature/Ledger/ReportPdfExportTest.php tests/Feature/Api/V1/Ledger/ReportApiTest.php
git commit -m "Migrate reports to shared-core web and API architecture"
```

After this task, what remains to migrate: optional Phase 7 only.

---

### Task 12: Optional Phase 7 Type Contracts And Cleanup

**Files:**

- Modify: `composer.json`
- Create: `app/Providers/TypeScriptTransformerServiceProvider.php`
- Modify: `bootstrap/providers.php`
- Modify: generated TypeScript output destination chosen during install
- Optionally modify: `resources/js/types/index.ts`
- Optionally modify: `resources/js/types/ledger.ts`
- Create: `tests/Feature/Architecture/TypeContractGenerationTest.php`

- [ ] **Step 1: Write a failing smoke test or command-level verification target for generated contracts**

The goal is to prove generated types can be produced and consumed without creating a second competing manual type system.

- [ ] **Step 2: Install `spatie/laravel-typescript-transformer` only if the user still wants Phase 7**

Run:

```bash
composer require spatie/laravel-typescript-transformer
php artisan typescript:install --no-interaction
```

- [ ] **Step 3: Configure the provider for Laravel Data output classes only**

Do not generate broad unrelated types by default.

- [ ] **Step 4: Run generation and verify frontend compatibility**

Run:

```bash
php artisan typescript:transform
npm run lint
```

- [ ] **Step 5: If generated types are clearly better, narrow or delete redundant manual types**

Do this only where the migration reduces confusion.

- [ ] **Step 6: Run full verification and commit Phase 7 separately**

Run:

```bash
vendor/bin/pint --dirty --format agent
npm run lint
php artisan test --compact
```

Commit:

```bash
git add composer.json bootstrap/providers.php app/Providers/TypeScriptTransformerServiceProvider.php resources/js/types tests/Feature/Architecture/TypeContractGenerationTest.php
git commit -m "Add generated TypeScript contracts for shared-core outputs"
```

---

## Verification Checklist Per Module Slice

Before moving to the next task:

- [ ] All new or changed Pest feature tests for the slice pass
- [ ] `vendor/bin/pint --dirty --format agent` has been run if PHP changed
- [ ] `npm run lint` has been run if JS or TS changed
- [ ] `php artisan wayfinder:generate --with-form --no-interaction` has been run if controller signatures or browser routes changed
- [ ] Any generated Wayfinder output under `resources/js/actions` and `resources/js/routes` is verified cleanly by lint or build checks
- [ ] The slice leaves browser pages pure Inertia and does not add frontend `/api/*` calls
- [ ] The slice adds or updates explicit exception-translation assertions for both web and API transport behavior when shared-core domain failures are possible
- [ ] The slice reports the remaining modules still left to migrate

## Final Verification After Reports Or Phase 7

Run the smallest sensible comprehensive suite after the last required module is done:

```bash
php artisan test --compact tests/Feature/Ledger/AccountTest.php tests/Feature/Ledger/AccountCrudTest.php tests/Feature/Ledger/AccountExportTest.php tests/Feature/Ledger/BillTest.php tests/Feature/Ledger/BillCrudTest.php tests/Feature/Ledger/BillIndexAccountAndHistoryTest.php tests/Feature/Ledger/BillServiceTest.php tests/Feature/Ledger/TransactionTest.php tests/Feature/Ledger/TransactionCrudTest.php tests/Feature/Ledger/TransactionPageTest.php tests/Feature/Ledger/TransactionSearchTest.php tests/Feature/Ledger/TransactionTypeConversionTest.php tests/Feature/Ledger/TransactionBulkActionTest.php tests/Feature/Ledger/TransactionAmountGuardrailTest.php tests/Feature/Ledger/SplitTransactionTest.php tests/Feature/Ledger/AttachmentTest.php tests/Feature/Ledger/TransferAttachmentTest.php tests/Feature/Ledger/TransactionServiceTest.php tests/Feature/Ledger/TransactionSplitServiceTest.php tests/Feature/Ledger/ImportTest.php tests/Feature/Ledger/ImportMappingTest.php tests/Feature/Ledger/ReportTest.php tests/Feature/Ledger/ReportComparisonTest.php tests/Feature/Ledger/ReportPdfExportTest.php tests/Feature/Ledger/DashboardPageTest.php tests/Feature/Architecture/DomainExceptionTranslationTest.php tests/Feature/Architecture/InertiaExceptionTranslationTest.php tests/Feature/Api/V1/Ledger/AccountApiTest.php tests/Feature/Api/V1/Ledger/BillApiTest.php tests/Feature/Api/V1/Ledger/TransactionApiTest.php tests/Feature/Api/V1/Ledger/ImportApiTest.php tests/Feature/Api/V1/Ledger/ReportApiTest.php
vendor/bin/pint --dirty --format agent
php artisan wayfinder:generate --with-form --no-interaction
npm run lint
```

If the full suite is still too slow, run this exact command set at minimum before calling the rollout complete.

## Completion State Definition

The required migration is complete only when all of the following are true:

1. Accounts, Bills, Transactions, Import, and Reports each have web controllers acting as thin transport adapters.
2. Matching API V1 ledger controllers exist for each remaining module where a non-browser first-party surface is intended.
3. Remaining browser-facing product pages still use web routes and Inertia semantics only.
4. Remaining legacy `FormRequest` usage for these modules has been removed or intentionally documented as an exception.
5. Remaining legacy `BillService`, `TransactionService`, and `ReportService` orchestration has been deleted or reduced to zero-logic adapters scheduled for removal.
6. The user has been told, after each finished module group, exactly what remains to migrate.
