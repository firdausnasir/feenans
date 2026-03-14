# Phase 2 — Returning User Experience Fixes: Implementation Plan

> **Goal**: Fix bugs and usability issues that affect daily-use workflows for users who already have data.
>
> **Prerequisites**: Phase 1 complete (especially toast notifications and branding)
>
> **Estimated scope**: 11 tasks

---

## Task 2.1 — Add Transfer Tab to Edit Transaction Modal

**Priority**: High
**Effort**: Medium

### Current State
- Edit modal shows Expense and Income tabs only — no Transfer tab
- Users cannot maintain a transaction as a transfer when editing
- Create modal correctly has all three tabs

### Implementation Steps

1. **Mirror create modal structure** — In the edit transaction component, add the Transfer tab matching the create modal's implementation.
2. **Handle type changes** — When switching from Expense/Income to Transfer:
   - Show destination account dropdown
   - Create the paired transaction automatically on save
3. **Handle Transfer → Expense/Income** — Delete the paired transaction when converting away from transfer.
4. **Backend support** — In `TransactionController@update` and `TransactionService`, handle type changes:
   - If changing to Transfer: create paired transaction, link via `transfer_pair_id` or equivalent
   - If changing from Transfer: remove paired transaction
5. **Pre-fill destination account** — When editing an existing transfer, pre-fill the destination account from the paired transaction.

### Files to Modify
- `resources/js/components/add-transaction-modal.tsx` — Add Transfer tab to edit mode
- `app/Http/Controllers/Ledger/TransactionController.php` — Handle type changes in `update()`
- `app/Services/TransactionService.php` — Type conversion logic
- `app/Http/Requests/UpdateTransactionRequest.php` — Validate transfer fields conditionally

### Testing
- Feature test: edit expense → transfer, verify paired transaction created
- Feature test: edit transfer → expense, verify paired transaction removed
- Feature test: edit transfer destination account, verify both transactions updated

---

## Task 2.2 — Prevent Transfers to Same Account

**Priority**: High (data integrity)
**Effort**: Small

### Implementation Steps

1. **Frontend validation** — In the transfer form, filter the destination account dropdown to exclude the selected source account (and vice versa).
2. **Backend validation** — In `StoreTransactionRequest` and `UpdateTransactionRequest`, add a custom rule:
   ```php
   'destination_account_id' => ['required_if:type,transfer', 'different:account_id']
   ```
3. **Custom error message**: `"Source and destination accounts must be different."`

### Files to Modify
- `resources/js/components/add-transaction-modal.tsx` — Filter dropdown options
- `app/Http/Requests/StoreTransactionRequest.php` — Add `different` rule
- `app/Http/Requests/UpdateTransactionRequest.php` — Add `different` rule

### Testing
- Feature test: submit transfer with same source and destination, assert validation error
- Feature test: verify dropdown excludes selected account on frontend

---

## Task 2.3 — Add Edit and Delete to Categories

**Priority**: High
**Effort**: Medium

### Current State
- Categories display beautifully with hierarchical list and color dots
- No edit or delete actions on individual categories
- Users can add but cannot rename, re-color, re-parent, or delete

### Implementation Steps

1. **Add action buttons to each category row** — Hover menu or always-visible icons: Edit (pencil) and Delete (trash).
2. **Edit modal** — Opens a form with:
   - Name (text input)
   - Color (color picker)
   - Parent assignment (dropdown — only for subcategories)
   - Type is not editable (expense stays expense)
3. **Delete with context** — Confirmation dialog:
   - Shows transaction count: `"This category has 23 transactions. What should happen to them?"`
   - Options: Reassign to another category (picker) OR leave uncategorized
   - For parents: warn that children will also be affected
4. **Backend endpoints** — Add/verify `update` and `destroy` methods in `CategoryController`:
   - `PUT /ledgers/{ledger}/categories/{category}` — Update name, color, parent_id
   - `DELETE /ledgers/{ledger}/categories/{category}` — With reassignment option
5. **Drag-to-reorder** — If `position` column exists in categories table, implement drag reordering using a lightweight library.

### Files to Modify
- `resources/js/pages/ledgers/categories/index.tsx` — Add action buttons, edit modal, delete dialog
- `app/Http/Controllers/Ledger/CategoryController.php` — `update()` and `destroy()` methods
- `app/Http/Requests/UpdateCategoryRequest.php` — New or update existing
- `routes/ledger.php` — Verify routes exist for category update/delete

### Testing
- Feature test: rename category, verify updated
- Feature test: change category color, verify updated
- Feature test: delete category with reassignment, verify transactions moved
- Feature test: delete parent category, verify children handled

---

## Task 2.4 — Payee Edit, Rename, and Merge

**Priority**: Medium
**Effort**: Medium

### Current State
- Payees page shows Name, Transaction Count, Delete only
- No rename, no merge, clicking name does nothing

### Implementation Steps

1. **Inline rename** — Click payee name → editable text field → save on blur/Enter.
2. **Merge flow** — Select two payees via checkboxes → "Merge" button:
   - Show dialog: "Merge {source} into {target}?"
   - All transactions from source reassign to target
   - Source payee is deleted
3. **Search/filter** — Add search bar above the payee list.
4. **Clickable navigation** — Click payee name navigates to Transactions filtered by that payee.
5. **Backend endpoints**:
   - `PUT /ledgers/{ledger}/payees/{payee}` — Rename
   - `POST /ledgers/{ledger}/payees/merge` — Merge two payees

### Files to Modify
- `resources/js/pages/ledgers/payees/index.tsx` — Inline edit, merge UI, search, click navigation
- `app/Http/Controllers/Ledger/PayeeController.php` — `update()`, `merge()` methods
- `app/Http/Requests/MergePayeesRequest.php` — New request class
- `routes/ledger.php` — Add merge route

### Testing
- Feature test: rename payee, verify name updated in transactions
- Feature test: merge payees, verify all transactions reassigned and source deleted
- Feature test: search payees by name

---

## Task 2.5 — Show Account and Payment History on Bills

**Priority**: Medium
**Effort**: Medium

### Current State
- Bills table shows Name, Amount, Recurrence, Next Due, Status, Auto, actions
- No account column, no payment history

### Implementation Steps

1. **Add Account column** — Show which account each bill charges to.
2. **Expandable rows** — Click bill row to expand and show payment history:
   - List of past transactions created by this bill (date, amount, account)
   - Query `transactions` where `bill_id` matches
3. **Auto badge** — Bills with auto-pay get clear "Auto" badge with tooltip.
4. **Backend** — Eager load `account` relationship and `transactions` (latest 10) for each bill.

### Files to Modify
- `resources/js/pages/ledgers/bills/index.tsx` — Add column, expandable rows
- `app/Http/Controllers/Ledger/BillController.php` — Eager load relationships
- Bill model if needed for relationship definitions

### Testing
- Feature test: verify bill index includes account name
- Feature test: verify payment history returns correct transactions

---

## Task 2.6 — Expand Bulk Actions for Transactions

**Priority**: Medium
**Effort**: Medium

### Current State
- Checkboxes show "X selected", "Delete selected", "Clear selection"
- Only bulk delete available

### Implementation Steps

1. **Add bulk action buttons** — When transactions selected, show:
   - "Change category" → opens category picker modal
   - "Change account" → opens account picker modal
   - "Change payee" → opens payee picker modal
   - "Delete selected" → confirmation: `"Delete {n} transactions? This cannot be undone."`
2. **Backend endpoint** — `POST /ledgers/{ledger}/transactions/bulk-update`:
   ```php
   // Accept: { ids: [], action: 'change_category|change_account|change_payee', value: id }
   ```
3. **Bulk delete confirmation** — Add explicit count and warning dialog.

### Files to Modify
- `resources/js/pages/ledgers/transactions/index.tsx` — Bulk action UI
- `app/Http/Controllers/Ledger/TransactionController.php` — `bulkUpdate()` method
- `app/Http/Requests/BulkUpdateTransactionsRequest.php` — New request class
- `routes/ledger.php` — Add bulk update route

### Testing
- Feature test: bulk change category for 3 transactions, verify all updated
- Feature test: bulk delete with confirmation

---

## Task 2.7 — Make Dashboard Summary Cards Interactive

**Priority**: Medium
**Effort**: Small

### Implementation Steps

1. **Wrap cards in links** — In `dashboard.tsx`:
   - Income card → `/ledgers/{id}/transactions?type=income&cycle=current`
   - Expense card → `/ledgers/{id}/transactions?type=expense&cycle=current`
   - Net card → `/ledgers/{id}/reports`
2. **Add hover state** — `cursor-pointer`, subtle scale or background shift on hover.
3. **Use Inertia Link** — Wrap with `<Link>` for SPA navigation.

### Files to Modify
- `resources/js/pages/dashboard.tsx` — Wrap summary cards with links

### Testing
- Manual: click each card, verify correct navigation and filters applied

---

## Task 2.8 — Wire Up Transaction Text Search

**Priority**: Medium
**Effort**: Small

### Current State
- Search bar exists with placeholder "Search description or notes..."
- Unclear if it actually works

### Implementation Steps

1. **Verify current implementation** — Check if the search input already sends a query parameter to the backend.
2. **Frontend** — Add debounced search (300ms) that updates the URL with `?search=query` and triggers an Inertia reload.
3. **Backend** — In `TransactionController@index`, add search filter:
   ```php
   $query->when($request->search, fn($q, $search) =>
       $q->where('description', 'like', "%{$search}%")
         ->orWhere('notes', 'like', "%{$search}%")
   );
   ```
4. **Persist in URL** — Search query survives page refresh via URL parameter.
5. **Optional: Global search (Cmd+K)** — Use `cmdk` (already installed) for a global search accessible from any page. Can be Phase 4 enhancement.

### Files to Modify
- `resources/js/pages/ledgers/transactions/index.tsx` — Debounced search with URL params
- `app/Http/Controllers/Ledger/TransactionController.php` — Search filter in `index()`

### Testing
- Feature test: search for transaction by description, verify filtered results
- Feature test: search query persists in URL on reload

---

## Task 2.9 — Fix Transaction Context Menu Actions

**Priority**: Medium
**Effort**: Small

### Current State
- "..." menu has Edit, Duplicate, Delete
- Delete is greyed out in context menu (but available in Edit modal)
- Inconsistent behavior

### Implementation Steps

1. **Enable Delete** — Remove the disabled state from Delete in the context menu.
2. **Add confirmation dialog** — On Delete click, show: `"Delete this transaction?"` with Cancel/Delete buttons.
3. **Fix Duplicate** — Pre-fill Add Transaction modal with same data but today's date.
4. **Show toast on delete** — `"Transaction deleted"` with Undo option (5s window).

### Files to Modify
- `resources/js/pages/ledgers/transactions/index.tsx` — Fix context menu actions
- Transaction row component — Enable delete, add confirmation dialog

### Testing
- Feature test: delete via context menu, verify transaction removed
- Feature test: duplicate transaction, verify new modal opens with correct data and today's date

---

## Task 2.10 — Verify Cycle Navigation Updates All Widgets

**Priority**: Medium
**Effort**: Small

### Implementation Steps

1. **Audit dashboard** — Navigate to different cycles and check if ALL widgets update:
   - Summary cards (income/expense/net)
   - Trend chart
   - Upcoming bills
   - Top categories
   - Recent transactions
   - Account balances (these should NOT change with cycle — they're current balances)
2. **Fix any stuck widgets** — Ensure the cycle date range is passed to all data queries in `DashboardController`.
3. **Backend check** — Verify that `DashboardController` uses the cycle parameter for all aggregations.

### Files to Modify
- `app/Http/Controllers/DashboardController.php` — Verify all queries use cycle range
- `resources/js/pages/dashboard.tsx` — Verify all widgets receive and react to cycle prop

### Testing
- Feature test: request dashboard with different cycle, verify all data scoped to that cycle
- Manual: navigate cycles, visually verify all widgets update

---

## Task 2.11 — Build Reports Comparison Mode

**Priority**: Medium
**Effort**: Medium

### Current State
- Reports page has "Compare" button — unclear if functional

### Implementation Steps

1. **Check current implementation** — Read `ReportController` and reports page to see if comparison logic exists.
2. **Comparison view**:
   - Side-by-side category breakdowns with percentage change (↑ 12%, ↓ 5%)
   - Monthly trend chart overlaying both periods
   - Summary: `"You spent 15% more on Food & Drinks this month compared to last month"`
3. **Backend** — Add comparison data to `ReportController`:
   - Accept `compare_start` and `compare_end` parameters
   - Return both periods' data plus calculated deltas
4. **Frontend** — New comparison layout in the reports page with dual charts and delta indicators.

### Files to Modify
- `app/Http/Controllers/Ledger/ReportController.php` — Comparison data endpoint
- `resources/js/pages/ledgers/reports/index.tsx` — Comparison UI
- Possibly new chart component for overlay comparison

### Testing
- Feature test: request comparison data, verify both periods returned with deltas
- Manual: visual check of comparison charts and percentage changes

---

## Implementation Order Summary

| Order | Task | Effort | Dependencies |
|-------|------|--------|-------------|
| 1 | 2.2 Prevent same-account transfer | Small | None |
| 2 | 2.7 Clickable dashboard cards | Small | None |
| 3 | 2.9 Fix context menu actions | Small | Phase 1 toasts |
| 4 | 2.8 Wire up text search | Small | None |
| 5 | 2.10 Verify cycle navigation | Small | None |
| 6 | 2.1 Transfer in edit modal | Medium | None |
| 7 | 2.3 Category edit/delete | Medium | None |
| 8 | 2.4 Payee edit/merge | Medium | None |
| 9 | 2.5 Bills account + history | Medium | None |
| 10 | 2.6 Bulk actions | Medium | None |
| 11 | 2.11 Reports comparison | Medium | None |

**Parallelization**: Tasks 2.2, 2.7, 2.8, 2.9, 2.10 are all small and independent — can be done in parallel. Tasks 2.3, 2.4, 2.5, 2.6 are independent medium tasks that can also be parallelized.
