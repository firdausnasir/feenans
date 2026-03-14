# Phase 3 — Feature Completeness: Implementation Plan

> **Goal**: Add missing features that make Feenans a complete personal finance tracker.
>
> **Prerequisites**: Phases 1 & 2 complete
>
> **Estimated scope**: 8 tasks (several are large)

---

## Task 3.1 — Rename "Bills" → "Recurring Transactions" + Support Recurring Income

**Priority**: High
**Effort**: Medium

### Current State
- Bills are exclusively expense-oriented
- No way to set up recurring income (salary, dividends, rental income)
- "Bills" label used everywhere

### Implementation Steps

1. **Rename user-facing strings** — Replace "Bills" with "Recurring Transactions" across:
   - Sidebar navigation
   - Page titles and breadcrumbs
   - Button labels
   - Toast messages
   - Keep internal code (`Bill` model, `bills` table, routes) unchanged for now
2. **Add type selector to bill form** — Expense / Income / Transfer radio buttons in `StoreBillRequest` and the bill creation form.
3. **Database migration** — Add `type` column to `bills` table (enum: expense, income, transfer, default: expense).
4. **Dashboard "Upcoming" section** — Show both upcoming expenses (red) and expected income (green), sorted by due date.
5. **Dynamic action labels**:
   - Expense: "Record Payment"
   - Income: "Record Income"
   - Transfer: "Record Transfer"
6. **BillService** — Update auto-pay logic to handle all types (create correct transaction type).
7. **Update PayBillDialog** (from Phase 1) — Adapt title and labels based on bill type.

### Files to Modify
- `resources/js/components/app-sidebar.tsx` — Rename navigation item
- `resources/js/pages/ledgers/bills/*.tsx` — Rename labels, add type selector
- `app/Http/Controllers/Ledger/BillController.php` — Handle type in CRUD
- `app/Http/Requests/StoreBillRequest.php` — Add type validation
- `app/Services/BillService.php` — Type-aware payment creation
- `app/Models/Bill.php` — Add type cast
- `database/migrations/xxxx_add_type_to_bills_table.php` — New migration
- `resources/js/pages/dashboard.tsx` — Color-coded upcoming section

### Testing
- Feature test: create recurring income bill, verify saved with correct type
- Feature test: auto-pay income bill, verify income transaction created
- Feature test: dashboard shows upcoming expenses and income differentiated

---

## Task 3.2 — Build Out the Budget System

**Priority**: High
**Effort**: Large

### Current State
- Budget page exists with empty state and "New Budget" button
- `Budget` model exists, `BudgetService` exists
- Feature skeleton is there but needs full implementation

### Implementation Steps

#### Step 1: Budget Creation Form
1. **Form fields**: Name, amount limit, linked category (optional — null = overall spending), period (monthly/weekly/yearly)
2. **Backend**: Verify `StoreBudgetRequest` validation, `BudgetController@store` logic
3. **Frontend**: Build form page/modal in `resources/js/pages/ledgers/budgets/`

#### Step 2: Budget List with Progress
1. **List view**: Budget name, category, allocated amount, spent amount (auto-calculated), remaining, progress bar
2. **Progress bar colors**: Green (<75%), Yellow (75-90%), Red (>90%)
3. **BudgetService** — `calculateSpent()` method: query transactions matching budget's category within the current period
4. **Backend**: Pass calculated data from `BudgetController@index`

#### Step 3: Dashboard Widget
1. **Top 3 budgets** with mini progress bars on dashboard
2. **Backend**: `DashboardController` includes top budgets with progress data

#### Step 4: Alerts
1. **Budget alerts** — When budget exceeds 80% or 100%, show notification (builds on Phase 3.8 notification system, or use in-page alerts initially)
2. **Visual indicators** on the budget list and dashboard widget

#### Step 5: Rollover (Optional Enhancement)
1. **Option to roll over** unspent budget to next period
2. **Database**: Add `rollover` boolean to budgets table
3. **BudgetService**: Calculate carry-over amount from previous period

### Files to Modify
- `resources/js/pages/ledgers/budgets/*.tsx` — Create/list/edit pages
- `app/Http/Controllers/Ledger/BudgetController.php` — Full CRUD + progress calculation
- `app/Services/BudgetService.php` — Spending calculation, period logic
- `app/Http/Requests/StoreBudgetRequest.php` — Validation
- `app/Http/Controllers/DashboardController.php` — Budget widget data
- `resources/js/pages/dashboard.tsx` — Budget widget component
- Possibly new migration for additional budget columns

### Testing
- Feature test: create budget, verify saved correctly
- Feature test: verify spent amount calculation against actual transactions
- Feature test: progress bar thresholds (green/yellow/red)
- Feature test: dashboard widget shows top 3 budgets

---

## Task 3.3 — Enhance CSV Import with Duplicate Detection

**Priority**: Medium
**Effort**: Medium

### Current State
- Working 3-step import wizard (Upload → Map Columns → Preview & Confirm)
- No duplicate detection, no saved mappings

### Implementation Steps

1. **Duplicate detection in Preview step**:
   - Before import, query existing transactions matching (date + amount + description)
   - Flag potential duplicates with yellow highlight
   - Add checkbox per row: "Import" / "Skip" (default skip for duplicates)
2. **Save column mappings**:
   - Store mapping configs in a `import_mappings` table or JSON column on ledger
   - On upload, offer: "Use saved mapping for {bank_name}?"
3. **Pre-built Malaysian bank mappings**:
   - Maybank, CIMB, RHB, Public Bank CSV formats
   - Auto-detect bank by CSV header structure
4. **Import history**:
   - New `imports` table: `id, ledger_id, filename, row_count, imported_at, mapping_used`
   - Show past imports list on the import page

### Files to Modify
- `resources/js/pages/ledgers/import/*.tsx` — Duplicate detection UI, saved mappings
- `app/Http/Controllers/Ledger/ImportController.php` — Duplicate check, mapping save/load
- New migration: `create_import_mappings_table`, `create_imports_table`
- `app/Models/ImportMapping.php`, `app/Models/Import.php` — New models

### Testing
- Feature test: import CSV with duplicates, verify flagged correctly
- Feature test: save and load column mapping
- Feature test: import history records created

---

## Task 3.4 — Improve Data Export

**Priority**: Medium
**Effort**: Medium

### Current State
- "Export CSV" button on Transactions page exists

### Implementation Steps

1. **Filter-aware CSV export** — Ensure export respects all active filters (date range, account, category, type, payee). Pass filter params to export endpoint.
2. **PDF report export** — On Reports page, add "Export PDF" button:
   - Use a server-side PDF library (e.g., `barryvdh/laravel-dompdf` or `spatie/laravel-pdf`)
   - Generate formatted monthly summary with charts rendered as images and category tables
3. **Account-level export** — On Account detail page, add "Export CSV" button for that account's transaction history.

### Files to Modify
- `app/Http/Controllers/Ledger/TransactionController.php` — Verify filter-aware export
- `app/Http/Controllers/Ledger/ReportController.php` — PDF export endpoint
- `app/Http/Controllers/Ledger/AccountController.php` — Account CSV export
- `resources/js/pages/ledgers/reports/index.tsx` — Export PDF button
- `resources/js/pages/ledgers/accounts/show.tsx` — Export CSV button
- `composer.json` — Add PDF generation package

### Testing
- Feature test: export CSV with filters, verify only filtered transactions included
- Feature test: export PDF report, verify file generated
- Feature test: export account transactions as CSV

---

## Task 3.5 — Add Tags for Transactions

**Priority**: Low
**Effort**: Medium

### Current State
- `Tag` model exists, `tags` and `tag_transaction` tables exist in migrations
- `TagController` exists
- Tags may already be partially implemented

### Implementation Steps

1. **Verify existing implementation** — Check how much of the tag system already works.
2. **Tag input on transaction form** — Multi-select tag picker (create new tags inline by typing).
3. **Tag management page** — List, create, edit (name + color), delete tags.
4. **Tag filter on Transactions** — Add tag filter to the transactions filter bar.
5. **Tag breakdown in Reports** — Optional: add a "By Tag" tab in reports.

### Files to Modify
- `resources/js/components/add-transaction-modal.tsx` — Tag picker
- `resources/js/pages/ledgers/tags/*.tsx` — Tag management (if not already done)
- `resources/js/pages/ledgers/transactions/index.tsx` — Tag filter
- `app/Http/Controllers/Ledger/TagController.php` — Verify CRUD
- `app/Http/Controllers/Ledger/TransactionController.php` — Tag filtering

### Testing
- Feature test: create transaction with tags, verify tags saved
- Feature test: filter transactions by tag
- Feature test: tag CRUD operations

---

## Task 3.6 — Verify and Complete Receipt/Attachment Upload

**Priority**: Low
**Effort**: Medium

### Current State
- Edit Transaction modal has "Attachments" section with "Choose Files" button
- `Attachment` model and `AttachmentController` exist
- Need to verify if it works end-to-end

### Implementation Steps

1. **Test current flow** — Upload a file, verify it's stored and retrievable.
2. **Fix if broken** — Ensure storage is configured (local or S3), file is saved, record created in `attachments` table.
3. **Thumbnails** — Show image thumbnails in transaction detail. Support JPG, PNG, PDF.
4. **Compression** — Add image compression for receipt photos (use Intervention Image or similar).
5. **Attachment indicator** — Show paperclip icon on transaction list rows that have attachments.

### Files to Modify
- `app/Http/Controllers/Ledger/AttachmentController.php` — Verify upload/download
- `resources/js/components/add-transaction-modal.tsx` — Attachment display, thumbnails
- `resources/js/pages/ledgers/transactions/index.tsx` — Paperclip indicator
- `config/filesystems.php` — Verify storage disk configuration

### Testing
- Feature test: upload attachment, verify stored and retrievable
- Feature test: delete attachment
- Feature test: verify paperclip indicator on transaction list

---

## Task 3.7 — Add Running Balance Column to Transactions

**Priority**: Medium
**Effort**: Small

### Implementation Steps

1. **Detect single-account filter** — When transactions are filtered to one account, show a "Balance" column.
2. **Calculate running balance** — Starting from account's initial balance, compute sequentially by date:
   - Income/transfer-in: add
   - Expense/transfer-out: subtract
3. **Backend** — In `TransactionController@index`, when single account filtered, calculate running balances server-side and include in response.
4. **Frontend** — Conditionally render Balance column.

### Files to Modify
- `app/Http/Controllers/Ledger/TransactionController.php` — Running balance calculation
- `resources/js/pages/ledgers/transactions/index.tsx` — Conditional Balance column

### Testing
- Feature test: filter to single account, verify running balances are correct
- Feature test: verify Balance column hidden when multiple accounts shown

---

## Task 3.8 — Build a Notification System

**Priority**: Medium
**Effort**: Large

### Current State
- Notification bell icon exists in header (`resources/js/components/notification-bell.tsx`)
- `NotificationController` exists
- `Notifications/` directory exists in `app/`
- May have skeleton implementation

### Implementation Steps

#### Step 1: Notification Infrastructure
1. **Verify Laravel notifications setup** — Check if `notifications` table migration exists.
2. **Create notification classes** in `app/Notifications/`:
   - `BillDueSoon` (3 days before)
   - `BillDueToday`
   - `BillOverdue`
   - `BudgetThresholdReached` (80%)
   - `BudgetExceeded` (100%)

#### Step 2: Wire Notification Bell
1. **NotificationController@index** — Return paginated notifications for the user.
2. **NotificationController@markRead** — Mark notification(s) as read.
3. **Frontend dropdown** — Wire `notification-bell.tsx` to show dropdown with recent notifications, mark-as-read on click.

#### Step 3: Automated Notification Generation
1. **Artisan command** — `php artisan bills:check-reminders` — runs daily, generates notifications for bills due within 3 days, today, or overdue.
2. **Schedule** — Register in `routes/console.php` to run daily.
3. **Budget checks** — After each transaction save, check if any budget crossed threshold.

#### Step 4: User Preferences
1. **Settings page** — Add notification preferences section.
2. **Preferences table/column** — Store which notification types are enabled per user.

### Files to Modify
- `app/Notifications/BillDueSoon.php` etc. — Notification classes
- `app/Http/Controllers/NotificationController.php` — API endpoints
- `resources/js/components/notification-bell.tsx` — Dropdown UI
- `app/Console/Commands/CheckBillReminders.php` — New command
- `routes/console.php` — Schedule command
- `app/Services/BudgetService.php` — Threshold check after transaction
- `resources/js/pages/settings/*.tsx` — Notification preferences

### Testing
- Feature test: bill due in 3 days triggers notification
- Feature test: bill overdue triggers notification
- Feature test: mark notification as read
- Feature test: budget exceeding 80% triggers notification

---

## Implementation Order Summary

| Order | Task | Effort | Dependencies |
|-------|------|--------|-------------|
| 1 | 3.7 Running balance | Small | None |
| 2 | 3.1 Recurring income | Medium | Phase 1 bill pay dialog |
| 3 | 3.5 Tags | Medium | Verify existing implementation first |
| 4 | 3.6 Attachments | Medium | Verify existing implementation first |
| 5 | 3.4 Export improvements | Medium | None |
| 6 | 3.3 Import enhancements | Medium | None |
| 7 | 3.2 Budget system | Large | None |
| 8 | 3.8 Notification system | Large | 3.2 (budget alerts) |

**Parallelization**: Tasks 3.7, 3.1, 3.5, 3.6 can all start in parallel. Tasks 3.3 and 3.4 are independent. Task 3.8 partially depends on 3.2 (for budget alerts).
