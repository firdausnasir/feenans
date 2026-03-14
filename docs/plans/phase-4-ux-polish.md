# Phase 4 — UX Polish & Delight: Implementation Plan

> **Goal**: Small improvements that make the product feel polished and professional.
>
> **Prerequisites**: Phases 1-3 complete (core features working)
>
> **Estimated scope**: 12 tasks, each small individually

---

## Task 4.1 — Celebrate the First Transaction

**Effort**: Small

### Implementation Steps

1. **Track first transaction** — Check if user has zero transactions before the new one is saved.
2. **Trigger celebration** — After first transaction save, return a flash flag (`first_transaction: true`) from the backend.
3. **Frontend animation** — On receiving the flag:
   - Show confetti animation (lightweight library like `canvas-confetti` or CSS keyframes)
   - Toast message: "Your first transaction! You're on your way to financial clarity."
4. **One-time only** — Store flag on user model or use the onboarding checklist state.

### Files to Modify
- `app/Services/TransactionService.php` — Detect first transaction
- `app/Http/Controllers/Ledger/TransactionController.php` — Flash `first_transaction` flag
- `resources/js/components/add-transaction-modal.tsx` — Trigger celebration
- `package.json` — Add `canvas-confetti` if needed

---

## Task 4.2 — Keyboard Shortcuts

**Effort**: Small

### Implementation Steps

1. **Create keyboard shortcut hook** — `resources/js/hooks/use-keyboard-shortcuts.ts`:
   - `N` → open New Transaction modal (when no input focused)
   - `Esc` → close any open modal
   - `Ctrl+K` / `Cmd+K` → global search (using `cmdk` already installed)
   - `←` / `→` → navigate cycles on dashboard
   - `?` → show shortcut help overlay
2. **Register in app layout** — Use `useEffect` with `keydown` listener in `app-layout.tsx`.
3. **Help overlay** — Simple modal listing all shortcuts, triggered by `?`.
4. **Global search** — Wire `cmdk` command palette for searching transactions, accounts, categories from any page.

### Files to Modify
- `resources/js/hooks/use-keyboard-shortcuts.ts` — New hook
- `resources/js/layouts/app-layout.tsx` — Register shortcuts
- `resources/js/components/keyboard-shortcuts-help.tsx` — New help overlay
- `resources/js/components/command-palette.tsx` — New or wire existing `cmdk` component

---

## Task 4.3 — "Stay Open for Rapid Entry" Toggle

**Effort**: Small

### Implementation Steps

1. **Add toggle to transaction modal** — Checkbox/switch: "Keep open for rapid entry"
2. **Behavior when ON** — After save: clear all fields, keep modal open, focus on amount field
3. **Behavior when OFF** (default) — After save: close modal, show toast
4. **Persist preference** — Store in `localStorage` so it remembers between sessions

### Files to Modify
- `resources/js/components/add-transaction-modal.tsx` — Add toggle, conditional close behavior

---

## Task 4.4 — Sample Data During Onboarding

**Effort**: Medium

### Implementation Steps

1. **Create sample data generator** — `app/Services/SampleDataService.php`:
   - 2 accounts (Maybank Savings, Cash Wallet)
   - 30-60 realistic Malaysian transactions across 2 months
   - Variety of categories (food, transport, utilities, shopping)
   - A few recurring bills
   - Realistic amounts in MYR
2. **Onboarding option** — During onboarding celebration step or as a dashboard empty state action: "Want to see how Feenans looks with data? Load sample transactions."
3. **Clean removal** — "Remove sample data" button in settings that deletes all sample-flagged records.
4. **Flag sample data** — Add `is_sample` boolean to transactions/accounts/bills or use a tag.

### Files to Modify
- `app/Services/SampleDataService.php` — New service
- `resources/js/pages/onboarding/celebration.tsx` — Sample data option
- `resources/js/pages/dashboard.tsx` — Alternative in empty state
- Database migration — Add `is_sample` flag if needed

---

## Task 4.5 — Interactive Dashboard Chart Elements

**Effort**: Small

### Implementation Steps

1. **Category bar click** — In "Top Expense Categories" chart, clicking a bar navigates to Transactions filtered by that category.
2. **Transaction row click** — Clicking a recent transaction opens its edit modal.
3. **Account card click** — Clicking an account card navigates to the account detail page.
4. **Use Recharts event handlers** — `onClick` prop on chart elements with Inertia router navigation.

### Files to Modify
- `resources/js/pages/dashboard.tsx` — Add click handlers to chart elements, transaction rows, account cards

---

## Task 4.6 — Account Reordering

**Effort**: Small

### Implementation Steps

1. **Drag-to-reorder** — Within each account type group on the Accounts page.
2. **Use a lightweight DnD library** — `@dnd-kit/core` or similar.
3. **Persist order** — `position` column on `accounts` table (add migration if not exists).
4. **Backend endpoint** — `POST /ledgers/{ledger}/accounts/reorder` accepting ordered IDs.

### Files to Modify
- `resources/js/pages/ledgers/accounts/index.tsx` — Drag-and-drop UI
- `app/Http/Controllers/Ledger/AccountController.php` — Reorder endpoint
- Database migration if `position` column needed
- `package.json` — Add DnD library if needed

---

## Task 4.7 — "Hide Account" Feature

**Effort**: Small

### Implementation Steps

1. **Add `is_hidden` column** — Boolean on `accounts` table (default false).
2. **Hide from UI** — Hidden accounts excluded from:
   - Account dropdowns (transaction form, bill form)
   - Dashboard totals
   - Accounts list (unless "Show hidden" toggle is ON)
3. **Toggle visibility** — Account context menu: "Hide" / "Unhide"
4. **"Show hidden accounts" toggle** — On Accounts page, toggle to reveal hidden accounts with a dimmed appearance.

### Files to Modify
- Database migration — Add `is_hidden` to accounts
- `app/Models/Account.php` — `scopeVisible()`, `scopeHidden()`
- `app/Http/Controllers/Ledger/AccountController.php` — Filter hidden by default
- `resources/js/pages/ledgers/accounts/index.tsx` — Toggle and dimmed display
- All forms with account dropdowns — Use visible accounts only

---

## Task 4.8 — Full Data Backup/Export in Settings

**Effort**: Medium

### Implementation Steps

1. **Export endpoint** — `GET /settings/export-data`:
   - Generate JSON containing: accounts, transactions, categories, bills, payees, budgets, tags
   - Or ZIP with separate CSV files per entity
2. **Frontend button** — In Settings > Danger Zone area: "Export all data" button
3. **File download** — Stream the file as a download response
4. **Include metadata** — Export timestamp, workspace name, currency

### Files to Modify
- `app/Http/Controllers/Settings/DataExportController.php` — New controller
- `resources/js/pages/settings/index.tsx` — Export button
- `routes/settings.php` — New route

---

## Task 4.9 — Income Breakdown in Reports

**Effort**: Small

### Implementation Steps

1. **Add income section** — Below the expense breakdown in Reports:
   - Income by category (donut chart)
   - Income by payee (table)
   - Income trends (monthly line)
2. **Backend** — `ReportController` already aggregates expenses — add parallel income aggregation.

### Files to Modify
- `app/Http/Controllers/Ledger/ReportController.php` — Income aggregation
- `resources/js/pages/ledgers/reports/index.tsx` — Income section with charts

---

## Task 4.10 — Income Overlay on Dashboard Trend Chart

**Effort**: Small

### Implementation Steps

1. **Improve visual distinction** — Make income and expense lines more visually distinct (different dash patterns, thicker lines, legend).
2. **Toggle visibility** — Add small toggles above the chart to show/hide income and expense lines independently.

### Files to Modify
- `resources/js/pages/dashboard.tsx` — Chart styling and toggle controls

---

## Task 4.11 — Explain Negative Account Balances

**Effort**: Small

### Implementation Steps

1. **Detect negative balance** — When rendering account balance, check if value is negative.
2. **Show info tooltip** — On hover: "This account has a negative balance, which can happen if you've logged more expenses than the initial balance you set."
3. **Use Radix Tooltip** — Already available in `resources/js/components/ui/`.

### Files to Modify
- `resources/js/pages/dashboard.tsx` — Account balance tooltips
- `resources/js/pages/ledgers/accounts/index.tsx` — Same

---

## Task 4.12 — "Uncategorized" Quick-Fix Flow

**Effort**: Small

### Implementation Steps

1. **Dashboard alert** — When uncategorized transactions exist, show alert: "You have {n} uncategorized transactions" with a link.
2. **Bulk categorization view** — Link goes to Transactions filtered by `category_id IS NULL` with the bulk "Change category" action from Phase 2.6 pre-highlighted.
3. **Backend** — `DashboardController` includes `uncategorized_count`.

### Files to Modify
- `app/Http/Controllers/DashboardController.php` — Count uncategorized transactions
- `resources/js/pages/dashboard.tsx` — Alert banner with link

---

## Implementation Order

These tasks are mostly independent and can be implemented in any order. Suggested grouping:

**Batch 1 — Quick wins (parallel)**:
- 4.2 Keyboard shortcuts
- 4.5 Interactive charts
- 4.7 Hide account
- 4.10 Income overlay
- 4.11 Negative balance tooltip
- 4.12 Uncategorized quick-fix

**Batch 2 — Slightly larger**:
- 4.1 First transaction celebration
- 4.3 Rapid entry toggle
- 4.6 Account reordering
- 4.9 Income breakdown in reports

**Batch 3 — Medium**:
- 4.4 Sample data
- 4.8 Full data export
