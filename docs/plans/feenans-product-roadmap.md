# Feenans Product Roadmap — Comprehensive Audit & Phased Action Plan

> **Context**: This document merges three audits performed on 14 March 2026:
> 1. A technical system audit (returning user with data on ledger 1)
> 2. A first-time user experience (FTUE) audit (signup → first transaction)
> 3. A returning user experience audit (all pages with existing data)
>
> **Purpose**: A product-level action plan. Each task describes **what the user sees today**, **what's wrong**, and **what the experience should be instead**. No code references — the implementing agent should scan the codebase to determine how to execute each task.
>
> **App**: Feenans — a personal finance tracker (expense/income/transfer tracking, recurring bills, reports, budgets)

---

## Current Product Snapshot

### What Exists Today
- **Dashboard**: Income/expense/net summary cards, cycle navigation arrows, upcoming bills with Pay buttons, daily expense & income trend chart, accounts grouped by type with balances, top expense categories bar chart
- **Accounts**: List grouped by type (Bank Account, Credit Card, E-Wallet, Cash) with Total Assets / Total Liabilities / Net Worth cards, individual account detail pages with 6-month balance trend + transaction history
- **Transactions**: Paginated list (25/page) with filters (date range, account, category, type, payee), search bar, checkboxes for bulk select, Export CSV button, Add transaction modal with Expense/Income/Transfer tabs, split transaction toggle, attachment upload area
- **Categories**: Hierarchical list (parent > child) with color-coded dots, Expense/Income tabs, + Add Category button at bottom
- **Bills**: Table with Name/Amount/Recurrence/Next Due/Status/Auto columns, Pay/Edit/Deactivate/Delete actions per row, New Bill form with recurrence configuration
- **Budgets**: Page exists with empty state ("No budgets yet") and New Budget button
- **Payees**: Simple list with transaction count per payee, Add Payee / Delete actions only
- **Reports**: Time range presets (This month, Last month, 3 months, 6 months, This year, Compare), account filter, monthly trend bar chart (income/expense/net), category breakdown donut chart with percentages and subcategory drill-down, payee breakdown table, statement cycles section
- **Import**: 3-step wizard (Upload CSV → Map Columns → Preview & Confirm), drag-and-drop file upload
- **Settings**: Workspace name, currency code, cycle start day, account types management, Danger Zone with delete

### Scorecard (Pre-Improvement)

| Area | First-Timer | Returning User | What Hurts |
|------|------------|---------------|------------|
| First impression | 2/10 | — | Default framework page, zero branding |
| Signup | 6/10 | — | Works but generic |
| Onboarding | 1/10 | — | Non-existent |
| Time to value | 3/10 | 7/10 | ~4 min with confusion vs smooth once set up |
| Dashboard | 4/10 | 8/10 | Empty state is a wall of zeros |
| Transaction entry | 5/10 | 6/10 | No success feedback, duplicate trap |
| Transaction management | — | 7/10 | Edit date bug, limited bulk actions |
| Bills | — | 7/10 | Pay has no confirmation, no account shown |
| Reports | — | 8/10 | Strong but missing comparison view |
| Categories | 2/10 | 5/10 | Can't edit or delete individual categories |
| Error handling | 2/10 | 4/10 | Raw developer-facing validation messages |

---

## PHASE 1 — Critical Fixes

> **Goal**: Fix broken flows, dangerous UX traps, and missing feedback. No new features — just make what exists work correctly and safely.

### 1.1 Build an Onboarding Wizard

**What happens today**: After registering, the user lands on a "Create ledger" page with a name field (confusing placeholder that looks pre-filled), a free-text currency input, and a cycle start day field. No explanation of what these mean. No welcome message. The word "ledger" means nothing to most people.

**What it should be**: A friendly, guided multi-step setup flow:

1. **Welcome screen**: "Welcome to Feenans! Let's set up your finances in under 2 minutes." Show a progress indicator.
2. **Name your space**: "What should we call your finance tracker?" Pre-fill "My Finances" as an actual default value (not a placeholder). Currency should be a searchable dropdown showing "Malaysian Ringgit (MYR)", "US Dollar (USD)", etc. — not a free-text field. Cycle start day needs a plain-language explanation: "When does your budget month start? Most people use the 1st."
3. **Add your accounts**: "Where do you keep your money?" Show quick-select buttons for common Malaysian banks (Maybank, CIMB, RHB, Public Bank, etc.) plus Cash, Touch 'n Go, GrabPay. Each button auto-fills the name and account type. Let users add initial balance. Include "Skip for now."
4. **Choose your categories**: Offer presets — "Malaysian Starter Pack" (10+ parent categories with 40+ subcategories, all color-coded), "Minimal" (5 basic), or "Start Empty." Show a preview of what gets created.
5. **First transaction** (optional): "Try adding your first expense!" Pre-filled hint transaction. "Skip — I'll do this later."
6. **Celebration**: "You're all set!" Redirect to the populated dashboard.

The user should be able to leave mid-flow and resume later. Each step should save progress.

### 1.2 Fix the "Add Transaction" Dead End

**What happens today**: A new user clicks "Add transaction" on the empty dashboard. The modal opens, but the Account dropdown is empty (no accounts created yet). Clicking Save produces a raw error: "The account id field is required." The user is stuck.

**What it should be**: If no accounts exist, the transaction modal should show a friendly message: "You need at least one account first. Let's create one!" with a button that takes them to account creation. On the dashboard, if no accounts exist, change the "Add transaction" button to "Set up your first account."

### 1.3 Add Success Feedback Everywhere (Toast Notifications)

**What happens today**: After saving a transaction, the modal stays open with the same data filled in. No toast, no success message, no visual confirmation. Users think it didn't save and click "Save" again — creating duplicate transactions. This same lack of feedback applies to creating accounts, paying bills, deleting items, and saving settings.

**What it should be**: Every action that changes data should show a brief success toast:
- Transaction saved: "Expense saved — RM 15.50" (auto-dismiss after 3 seconds)
- Transaction deleted: "Transaction deleted" (with an Undo option for 5 seconds)
- Bill paid: "Telekom Unifi paid — RM 119.00 from Maybank Savings"
- Account created: "Maybank Savings added"
- Settings saved: "Settings updated"

After saving a transaction, the modal should close by default and show the toast on the underlying page. The form should not retain the previously submitted data.

### 1.4 Fix Transaction Edit — Date Field Is Empty

**What happens today**: When editing an existing transaction, the date field shows "dd/mm/yyyy" (the browser placeholder) instead of the transaction's actual date. All other fields (amount, account, category, payee, description) are pre-filled correctly, but the date is blank.

**What it should be**: The date field must show the transaction's existing date when editing. This is a data binding bug — the date value is either not being passed to the form or is in the wrong format.

### 1.5 Replace the Landing Page

**What happens today**: Visiting the app URL shows the default Laravel welcome page — Laravel logo, "Read the Documentation" link, "Deploy now" button, version information. There is zero indication this is a finance app called Feenans.

**What it should be**: A proper product landing page:
- Product name "Feenans" with a wordmark or logo
- Tagline: something like "Track your money, your way."
- 3-4 feature highlights with icons (multi-account tracking, smart recurring bills, visual reports, etc.)
- Prominent "Get Started" button → registration
- "Already have an account? Log in" link
- Matches the app's dark theme aesthetic

### 1.6 Fix All Product Branding

**What happens today**: Every browser tab says "Page Name - Laravel". The favicon is the Laravel logo. The word "Feenans" appears nowhere in the entire UI. The login and register pages are generic.

**What it should be**:
- All page titles: "Dashboard — Feenans", "Transactions — Feenans", etc.
- Favicon: a branded icon (even a simple "F" in the app's accent color)
- Sidebar header: show "Feenans" wordmark or logo above the workspace name
- Auth pages (login, register): show Feenans branding, not generic forms

### 1.7 Replace Currency Free-Text Input with Searchable Dropdown

**What happens today**: When creating a workspace, the currency field is a raw text input. Users can type "RM", "Ringgit", "usd", "dollars", or anything. There's no validation until submission. Once saved, the Settings page makes currency read-only — so a wrong value is permanent.

**What it should be**: A searchable dropdown showing "Malaysian Ringgit (MYR)", "US Dollar (USD)", "Singapore Dollar (SGD)", etc. Default to MYR. Validate that the submitted value is a real ISO 4217 currency code. In Settings, allow currency changes only if the workspace has zero transactions (with a clear warning about what changing currency means).

### 1.8 Replace "Ledger" Terminology Everywhere

**What happens today**: The term "ledger" appears in the creation page, settings ("Delete this ledger"), breadcrumbs, and internal references. 95% of non-accounting users don't know what a ledger is.

**What it should be**: Replace every user-facing instance of "ledger" with "workspace" (or simply remove the concept if the product is single-user focused and auto-create one workspace per user). Examples:
- "Create ledger" → "Create your workspace" or just fold this into onboarding
- "Delete this ledger" → "Delete this workspace"
- Settings title: "Workspace Settings"
- The workspace name ("My Finances") is already being used well in the sidebar — keep that

### 1.9 Humanize All Validation Errors

**What happens today**: Errors display as developer-facing messages: "The account id field is required.", "The amount field must be a number.", "The transaction date field is required." These are confusing and unprofessional.

**What it should be**: Every validation message should be human-friendly:
- "The account id field is required." → "Please select an account."
- "The amount field must be a number." → "Please enter a valid amount."
- "The category id field is required." → "Please choose a category."
- "The transaction date field is required." → "Please select a date."
- "The name field is required." → "Please enter a name."

Audit every form in the app (transaction, account, bill, category, payee, budget, settings, workspace creation) and replace all messages.

### 1.10 Fix the Placeholder Pretending to Be a Value

**What happens today**: On the workspace creation page, the "Name" field shows "Personal" in grey. It looks like the field is already filled in. Users try to submit without typing anything and hit browser validation. This is a known anti-pattern.

**What it should be**: Either:
- (Better) Pre-fill "My Finances" as the actual default value — users who want the default just proceed, those who want custom can clear and retype
- Or change the placeholder to something clearly placeholder-like: "e.g., My Finances"

### 1.11 Add Confirmation Dialog Before Paying a Bill

**What happens today**: Clicking "Pay" on a bill in the dashboard or bills page immediately creates a transaction. No confirmation dialog. No indication of which account will be charged. No success feedback. The bill simply vanishes from the upcoming list. The transaction is backdated to the bill's due date, not today's date if they differ.

**What it should be**: A confirmation modal before payment:
- Title: "Pay Telekom Unifi?"
- Show amount: RM 119.00
- Account dropdown (defaulting to the bill's configured account, changeable)
- Date picker (defaulting to today, changeable)
- "Pay Now" / "Cancel" buttons
- After payment: success toast showing "Telekom Unifi paid — RM 119.00 from Maybank Savings"

### 1.12 Enrich Default Categories for New Users

**What happens today**: A new workspace gets only 5 categories — Food, Transport, Bills (expense) and Salary, Bonus (income). No subcategories, no colors. Meanwhile, the demo user's workspace has 10+ parent categories with 40+ subcategories, all beautifully color-coded. The gap is enormous and makes the new user experience feel barren.

**What it should be**: The default category set for new users should be the same rich set the demo user has:
- **Expense**: Food & Drinks (Groceries, Dining Out, Coffee & Tea, Snacks), Transport (Grab/Ride-hailing, Fuel, Toll, Parking, Public Transport), Shopping (Clothing, Electronics, Online Shopping, Home Goods), Utilities (Electricity, Water, Internet, Mobile Plan), Entertainment (Streaming, Movies, Games, Events), Health (Pharmacy, Doctor/Clinic, Gym, Supplements), Personal Care (Haircut, Skincare, Toiletries), Education (Books, Courses, Tuition), Home (Rent, Maintenance, Furniture)
- **Income**: Salary, Bonus, Freelance, Dividends, Other Income
- All with distinct colors per parent category

### 1.13 Design Proper Empty States for Every Page

**What happens today**: When a new user lands on the dashboard, they see three cards showing "RM 0.00", an empty trend chart with a meaningless Y-axis (0-4), "No upcoming bills", "No accounts yet", and "No expenses this cycle." It looks broken, not empty.

**What it should be**: Every page should have a purposeful empty state:

- **Dashboard**: Replace the empty chart with a helpful message: "Your spending trends will appear here once you start logging transactions." Add a getting-started checklist:
  - ☐ Create your first account
  - ☐ Add your first transaction
  - ☐ Set up a recurring bill
  - ☐ Create a budget
  Each item links to the relevant action. Auto-dismiss after 5+ transactions.
- **Transactions**: "No transactions yet. Start tracking your spending!" with Add Transaction button
- **Accounts**: "Add your bank accounts and wallets to start tracking." with New Account button
- **Bills**: "Set up recurring bills to never miss a payment." with New Bill button
- **Payees**: "Payees will appear here as you create transactions."
- **Reports**: "Add some transactions first to see your spending insights."
- **Budgets**: Already has a good empty state — use this as the template for all others

---

## PHASE 2 — Returning User Experience Fixes

> **Goal**: Fix bugs and usability issues that affect daily-use workflows for users who already have data.

### 2.1 Add Transfer Option to Edit Transaction Modal

**What happens today**: The Edit Transaction modal shows Expense and Income type tabs, but no Transfer tab. If a user needs to edit a transfer transaction, they can't keep it as a transfer — it can only become an expense or income. The Create Transaction modal correctly has all three tabs.

**What it should be**: The edit modal should mirror the create modal exactly — show Expense, Income, and Transfer tabs. When Transfer is selected, show the destination account dropdown. Handle type changes gracefully (e.g., converting an expense to a transfer should create the paired transaction automatically).

### 2.2 Prevent Transfers to the Same Account

**What happens today**: The transfer form allows selecting the same account for both source and destination. You can transfer from "Maybank Savings" to "Maybank Savings."

**What it should be**: The destination account dropdown should exclude the currently selected source account (and vice versa). If somehow both are set to the same account, show a validation error: "Source and destination accounts must be different."

### 2.3 Add Edit and Delete Actions to Categories

**What happens today**: The categories page displays a beautiful hierarchical list with color-coded dots, but there are no edit or delete actions on individual categories. Users can add categories but cannot rename, re-color, re-parent, or delete them from this page.

**What it should be**:
- Each category row should have a hover menu or icons: Edit and Delete
- **Edit**: Opens a modal or inline form to change name, color, parent assignment, and icon
- **Delete**: Shows a confirmation with context: "This category has 23 transactions. What should happen to them?" Options: reassign to another category, or leave them uncategorized
- For parent categories: warn that deleting affects all children
- Add drag-to-reorder (the position/ordering capability exists in the data model)

### 2.4 Add Edit, Rename, and Merge for Payees

**What happens today**: The payees page shows Name, Transaction Count, and Delete. There's no way to rename a payee (fix a typo) or merge duplicates (e.g., "Starbucks" and "STARBUCKS" are treated as separate payees forever). Clicking a payee name doesn't do anything.

**What it should be**:
- Inline rename: click a payee name to make it editable
- Merge: select two payees → merge into one (all transactions from the source reassign to the target, then source is deleted)
- Search/filter bar as the list grows
- Clicking a payee name should navigate to Transactions filtered by that payee

### 2.5 Show Account and Payment History on Bills

**What happens today**: The bills table shows Name, Amount, Recurrence, Next Due, Status, Auto, and action buttons. There is no indication of which account each bill charges to. No history of past payments for each bill.

**What it should be**:
- Add an "Account" column showing which account the bill is linked to (e.g., "Maybank Savings")
- Make each bill row expandable to show payment history: a list of past transactions created by this bill (date, amount, account)
- Bills with auto-pay enabled should have a clear "Auto" badge with tooltip: "This bill is paid automatically on the due date"

### 2.6 Expand Bulk Actions for Transactions

**What happens today**: Selecting transactions via checkboxes shows "X selected", "Delete selected", and "Clear selection." Bulk delete is the only available bulk action.

**What it should be**:
- "Change category" — opens a category picker, applies to all selected
- "Change account" — opens an account picker
- "Change payee" — opens a payee picker
- "Delete selected" — with confirmation: "Delete 5 transactions? This cannot be undone."

### 2.7 Make Dashboard Summary Cards Interactive

**What happens today**: The Income, Expense, and Net cards at the top of the dashboard are static — display only.

**What it should be**:
- Click "Income" → navigate to Transactions filtered by income for the current cycle
- Click "Expense" → navigate to Transactions filtered by expense for the current cycle
- Click "Net" → navigate to Reports
- Show cursor pointer and subtle hover state to signal clickability

### 2.8 Wire Up Transaction Text Search

**What happens today**: The transactions page has a search bar with placeholder "Search description or notes..." It's unclear if this actually works or is just a UI placeholder.

**What it should be**: The search bar should filter transactions in real-time (with debounce) by matching against description and notes fields. The search query should persist as a URL parameter so it survives page refreshes. Consider adding a global search shortcut (Ctrl+K / Cmd+K) accessible from any page.

### 2.9 Fix Transaction Context Menu Actions

**What happens today**: Each transaction row has a "..." menu with Edit, Duplicate, and Delete. Delete is greyed out in the context menu (but available inside the Edit modal). This is inconsistent.

**What it should be**:
- Delete should be enabled in the context menu, with a confirmation dialog
- Duplicate should pre-fill the Add Transaction modal with the same data but today's date
- Consider also showing the context menu on right-click

### 2.10 Verify Cycle Navigation Updates All Dashboard Widgets

**What works today**: The dashboard has cycle navigation arrows (< Previous Cycle | Current Cycle | Next >) that change the displayed date range.

**What to verify**: Ensure that navigating to a different cycle updates ALL dashboard widgets — summary cards, trend chart, upcoming bills, top categories, recent transactions, and accounts. If any widget stays stuck on the current cycle, fix it.

### 2.11 Build Reports Comparison Mode

**What happens today**: The Reports page has time presets including a "Compare" button, but it's unclear if this actually performs a comparison or just sits there.

**What it should be**: Compare mode should show the selected period alongside the previous equivalent period:
- Side-by-side category breakdowns with percentage change indicators (↑ 12%, ↓ 5%)
- Monthly trend chart overlaying both periods
- Summary: "You spent 15% more on Food & Drinks this month compared to last month"

---

## PHASE 3 — Feature Completeness

> **Goal**: Add missing features that make Feenans a complete personal finance tracker.

### 3.1 Rename "Bills" → "Recurring Transactions" + Support Recurring Income

**What's missing**: Bills are exclusively expense-oriented. There's no way to set up recurring income (salary deposited on the 25th, monthly dividend, rental income). Users with predictable income can't automate it.

**What to build**:
- Rename "Bills" to "Recurring Transactions" everywhere (sidebar, breadcrumbs, page titles, buttons)
- Add a transaction type selector (Expense / Income / Transfer) to the recurring transaction form
- The dashboard's "Upcoming" section should show both upcoming expenses AND expected income, clearly differentiated (expense in red, income in green)
- Auto-pay logic should work for all types
- The "Pay" action label should change based on type: "Record Payment" for expenses, "Record Income" for income, "Record Transfer" for transfers

### 3.2 Build Out the Budget System

**What exists**: A Budgets page with an empty state and New Budget button. The feature skeleton is there but needs to be fully built.

**What to build**:
- Budget creation form: name, amount limit, linked category (optional — null means overall spending), period (monthly/weekly/yearly)
- Budget list page showing: budget name, category, allocated amount, spent amount (auto-calculated from actual transactions in the period), remaining amount, visual progress bar
- Progress bar color coding: green (under 75%), yellow (75-90%), red (over 90%)
- Dashboard widget: top 3 budgets with mini progress bars
- When a budget exceeds 80% or 100%, show an alert/notification
- Option to roll over unspent budget to the next period

### 3.3 Enhance CSV Import with Duplicate Detection

**What exists**: A working 3-step import wizard (Upload CSV → Map Columns → Preview & Confirm).

**What to improve**:
- In the Preview step, flag rows that match existing transactions (same date + amount + description) as potential duplicates. Let users skip or import them.
- "Save column mapping" — so returning users who import monthly bank statements don't re-map every time
- Pre-built column mappings for common Malaysian bank statement formats (Maybank, CIMB, RHB, Public Bank)
- Import history: show past imports with row counts, dates, and which file was used

### 3.4 Improve Data Export

**What exists**: An "Export CSV" button on the Transactions page.

**What to verify and improve**:
- The CSV export should respect all active filters (date range, account, category, type, payee) — only export the filtered subset, not everything
- Add PDF export to the Reports page: a formatted monthly summary with charts and category tables, suitable for printing or sharing
- Add CSV export option on the Accounts detail page (export that account's transaction history)

### 3.5 Add Tags for Transactions

**What's missing**: Transactions can only be assigned one category. There's no way to cross-categorize or add metadata like "business trip", "reimbursable", "shared expense", etc.

**What to build**:
- Tags that can be assigned to any transaction (multi-select, user can create new tags inline)
- Each tag has a name and optional color
- Tag filter on the Transactions page
- Tag breakdown option in Reports
- Common use cases: "reimbursable", "shared with partner", "business", "vacation", "wedding"

### 3.6 Verify and Complete Receipt/Attachment Upload

**What exists**: The Edit Transaction modal has an "Attachments" section with a "Choose Files" button and "No attachments yet." text.

**What to verify/build**:
- Test if uploading a file actually works end-to-end (stored, retrievable, viewable)
- If not working, build it: support image (JPG, PNG) and PDF uploads, show thumbnails in the transaction detail, allow viewing and downloading
- Compress receipt photos to save storage
- Show attachment indicator (paperclip icon) on the transactions list for entries that have attachments

### 3.7 Add Running Balance Column to Transactions

**What's missing**: The transactions list shows Date, Description, Account, Category, Payee, Amount. There's no running balance column — essential for reconciling with bank statements.

**What to build**: When transactions are filtered to a single account, show a "Balance" column displaying the account's running balance after each transaction. Start from the account's initial balance and compute sequentially. This column should only appear in single-account filter mode (not when viewing all accounts).

### 3.8 Build a Notification System

**What's missing**: Bills process silently. Users don't get reminders for upcoming or overdue bills. The notification bell icon exists in the header but doesn't seem wired up.

**What to build**:
- Wire the existing notification bell icon to show a dropdown with recent notifications
- Notification types: Bill due in 3 days, Bill due today, Bill overdue, Budget at 80%, Budget exceeded
- Bill reminders should run daily and generate notifications automatically
- Notification preferences in user settings (which notifications to receive)
- In-app notifications first; email can come later

---

## PHASE 4 — UX Polish & Delight

> **Goal**: Small improvements that make the product feel polished and professional.

### 4.1 Celebrate the First Transaction
When a user creates their very first transaction, show a brief celebration — confetti animation, or a friendly message: "Your first transaction! You're on your way to financial clarity." Small moments of delight boost retention.

### 4.2 Add Keyboard Shortcuts
- `N` — open New Transaction modal from any page
- `Esc` — close any open modal
- `Ctrl+K` / `Cmd+K` — global search
- `←` / `→` — navigate cycles on dashboard
- `?` — show keyboard shortcut help overlay

### 4.3 Add "Stay Open for Rapid Entry" Toggle
In the transaction modal, add a toggle: when ON, the modal stays open after save with cleared fields (for logging multiple transactions quickly). When OFF (default), the modal closes after save.

### 4.4 Offer Sample Data During Onboarding
"Want to see how Feenans looks with data? Load sample transactions." Generate 30-60 realistic transactions across 2 months so new users can explore a populated dashboard before committing to their own data.

### 4.5 Make Dashboard Chart Elements Interactive
- Clicking a category bar in "Top Expense Categories" → Transactions filtered by that category
- Clicking a recent transaction → open its edit modal
- Clicking an account card → navigate to that account's detail page

### 4.6 Add Account Reordering
Allow drag-to-reorder accounts within each account type group. Users want their most-used accounts at the top.

### 4.7 Add "Hide Account" Feature
For inactive accounts users don't want to delete (they have history). Hidden accounts don't appear in dropdowns, dashboard totals, or the accounts list — but are accessible via a "Show hidden accounts" toggle.

### 4.8 Add Full Data Backup/Export in Settings
An "Export all data" button that generates a JSON or ZIP containing everything: accounts, transactions, categories, bills, payees, budgets. Essential for peace of mind and data portability.

### 4.9 Add Income Breakdown to Reports
Reports currently only break down expenses by category. Add a separate section for income breakdown: income by category, income by payee, income trends.

### 4.10 Add Income Overlay to Dashboard Trend Chart
The daily trend chart shows expense and income as separate colored lines. This already works but the income line (blue) is harder to see. Make both lines visually distinct and add a toggle to show/hide each.

### 4.11 Explain Negative Account Balances
When an account shows a negative balance (e.g., Cash Wallet: -RM 105.15), show an info tooltip: "This account has a negative balance, which can happen if you've logged more expenses than the initial balance you set." Prevents user confusion.

### 4.12 Add "Uncategorized" Quick-Fix Flow
When transactions exist without a category (e.g., imported transactions), show a dashboard alert: "You have 12 uncategorized transactions" with a link to a bulk categorization view.

---

## PHASE 5 — Technical Foundations

> **Goal**: Improve the product's reliability, performance, and extensibility. These are invisible to users but critical for growth.

### 5.1 Database Performance
Add proper database indexes for frequently queried columns. As users accumulate thousands of transactions, queries will slow down without them. Key areas: transaction filtering, bill lookups, category aggregation.

### 5.2 Soft Deletes (Undo/Recover)
Currently all deletions are permanent. Implement soft deletion so deleted items go to a "Recently Deleted" section and can be recovered within 30 days. This protects users from accidental deletions.

### 5.3 Activity/Audit Log
No record of changes exists. If a transaction amount is edited, the original value is lost forever. Implement an activity log that tracks all create/update/delete operations with old and new values. Show this as a "History" section in transaction detail.

### 5.4 REST API
Currently the app only serves rendered pages. Build a JSON API layer to enable:
- Future mobile app
- Integration with automation tools (n8n, Zapier)
- Third-party widgets or CLI tools
- Programmatic data access

### 5.5 Transaction Amount Guardrails
The transaction form currently accepts negative numbers and zero. Add validation to enforce positive amounts only (the system handles the sign internally based on whether it's an expense, income, or transfer). Frontend should prevent typing negative values.

### 5.6 Automated Test Coverage
Write tests for all critical paths: transaction CRUD (all three types), transfer pair management, bill payment and schedule advancement, auto-bill processing, budget calculations, cycle boundary computation, import/export. This prevents regressions as features are added.

---

## Implementation Priority (Recommended Order)

| # | Task | Why First | Impact | Effort |
|---|------|-----------|--------|--------|
| 1 | 1.3 Toast notifications | Prevents the duplicate transaction trap — most dangerous current bug | Critical | Small |
| 2 | 1.4 Fix edit date bug | Data integrity issue — users unknowingly clear dates | Critical | Tiny |
| 3 | 1.9 Humanize errors | Every failed form submission looks broken | Critical | Small |
| 4 | 1.2 Fix Add Transaction dead end | First action new users try, and it fails | Critical | Small |
| 5 | 1.11 Bill pay confirmation | One-click irreversible action with no undo | Critical | Small |
| 6 | 1.6 Fix branding | Product looks like a framework demo | Critical | Small |
| 7 | 1.10 Fix placeholder trap | Users can't get past workspace creation | Critical | Tiny |
| 8 | 1.12 Enrich default categories | New user experience feels barren | High | Medium |
| 9 | 1.7 Currency dropdown | Permanent data quality issue | High | Small |
| 10 | 1.8 Kill "ledger" jargon | Confuses 95% of users | High | Small |
| 11 | 1.13 Empty states | Dashboard looks broken when empty | High | Medium |
| 12 | 1.1 Onboarding wizard | Biggest FTUE gap — no guidance at all | Highest | Large |
| 13 | 1.5 Landing page | First impression is a framework page | High | Medium |
| 14 | 2.1 Transfer in edit modal | Can't maintain transfers when editing | High | Medium |
| 15 | 2.3 Category edit/delete | Basic management missing | High | Medium |
| 16 | 2.8 Wire up text search | Search bar exists but may not work | Medium | Small |
| 17 | 2.4 Payee edit/merge | Can't fix typos or clean up duplicates | Medium | Medium |
| 18 | 2.7 Clickable dashboard cards | Easy win, makes dashboard more useful | Medium | Small |
| 19 | 2.5 Bills account + history | Users don't know which account is charged | Medium | Medium |
| 20 | 2.6 Bulk actions | Only bulk delete exists | Medium | Medium |
| 21 | 3.1 Recurring income | Half the financial picture is missing | High | Medium |
| 22 | 3.2 Budget system | Key feature for any finance app | High | Large |
| 23 | 3.3 Import enhancements | Duplicates are a real risk | Medium | Medium |
| 24 | 3.4 Export improvements | Filter-aware export needed | Medium | Medium |
| 25 | 3.7 Running balance | Essential for reconciliation | Medium | Small |
| 26 | 3.8 Notifications | Bills processed silently | Medium | Large |
| 27 | 3.5 Tags | Cross-categorization needed | Low | Medium |
| 28 | 3.6 Attachments | Receipt tracking is useful but not urgent | Low | Medium |
| 29+ | Phase 4 | Polish items | Low | Small each |
| 30+ | Phase 5 | Technical debt | Low-Med | Varies |
