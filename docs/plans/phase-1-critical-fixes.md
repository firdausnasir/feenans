# Phase 1 — Critical Fixes: Implementation Plan

> **Goal**: Fix broken flows, dangerous UX traps, and missing feedback. No new features — just make what exists work correctly and safely.
>
> **Estimated scope**: 13 tasks, ordered by implementation priority (most dangerous bugs first)

---

## Task 1.3 — Toast Notifications (Success Feedback Everywhere)

**Priority**: #1 — Prevents duplicate transaction trap (most dangerous current bug)
**Effort**: Small

### Current State
- Sonner (`sonner@2.0.7`) is already installed in `package.json`
- After saving a transaction, the modal stays open with same data — no feedback
- Users click Save again, creating duplicates
- Same issue across: accounts, bills, payees, categories, settings

### Implementation Steps

1. **Verify Sonner is wired up** — Check if `<Toaster />` is rendered in `resources/js/layouts/app-layout.tsx`. If not, add it.
2. **Add toast calls to all Inertia form submissions** — After each successful `router.post` / `router.put` / `router.delete`, call `toast.success()` with contextual messages:
   - Transaction saved: `"Expense saved — RM {amount}"`
   - Transaction deleted: `"Transaction deleted"` (with Undo action for 5s)
   - Bill paid: `"{bill_name} paid — RM {amount} from {account}"`
   - Account created: `"{name} added"`
   - Settings saved: `"Settings updated"`
3. **Close modal after successful transaction save** — In `resources/js/components/add-transaction-modal.tsx`, on `onSuccess` callback: close modal, reset form, show toast.
4. **Audit all forms** — Check every page component in `resources/js/pages/` for form submissions missing feedback.

### Files to Modify
- `resources/js/layouts/app-layout.tsx` — Ensure `<Toaster />` rendered
- `resources/js/components/add-transaction-modal.tsx` — Close + reset + toast on save
- `resources/js/pages/ledgers/accounts/*.tsx` — Toast on CRUD
- `resources/js/pages/ledgers/bills/*.tsx` — Toast on pay/create/edit/delete
- `resources/js/pages/ledgers/categories/*.tsx` — Toast on CRUD
- `resources/js/pages/ledgers/payees/*.tsx` — Toast on CRUD
- `resources/js/pages/settings/*.tsx` — Toast on save
- `resources/js/pages/dashboard.tsx` — Toast on bill pay

### Testing
- Feature test: verify Inertia flash messages set on successful operations
- Manual: confirm toast appears and modal closes after save

---

## Task 1.4 — Fix Edit Transaction Date Field

**Priority**: #2 — Data integrity issue
**Effort**: Tiny

### Current State
- Edit modal shows `dd/mm/yyyy` placeholder instead of the transaction's actual date
- Other fields (amount, account, category, payee, description) pre-fill correctly
- Likely a date format mismatch between backend (ISO 8601) and HTML date input

### Implementation Steps

1. **Trace the data flow** — Read `resources/js/components/add-transaction-modal.tsx` (or equivalent edit component) to find how `transaction_date` is bound to the date input.
2. **Fix format** — Ensure the date value is formatted as `YYYY-MM-DD` (HTML date input requirement). If using `react-day-picker`, ensure the Date object is properly constructed from the ISO string.
3. **Verify backend response** — Check the controller (`app/Http/Controllers/Ledger/TransactionController.php`) `edit` method to ensure `transaction_date` is included in the Inertia props.

### Files to Modify
- `resources/js/components/add-transaction-modal.tsx` (or edit transaction component) — Fix date binding
- Possibly `app/Http/Controllers/Ledger/TransactionController.php` — Ensure date is passed correctly

### Testing
- Feature test: `TransactionTest.php` — verify edit endpoint returns correct date format
- Manual: edit existing transaction, confirm date shows correctly

---

## Task 1.9 — Humanize All Validation Errors

**Priority**: #3 — Every failed form looks broken
**Effort**: Small

### Current State
- Errors like `"The account id field is required."` shown to users
- Raw Laravel validation messages with snake_case field names

### Implementation Steps

1. **Audit all Form Request classes** in `app/Http/Requests/` (~25 files)
2. **Add custom `messages()` method** to each Form Request:
   ```php
   public function messages(): array
   {
       return [
           'account_id.required' => 'Please select an account.',
           'amount.numeric' => 'Please enter a valid amount.',
           'transaction_date.required' => 'Please select a date.',
           'category_id.required' => 'Please choose a category.',
           'name.required' => 'Please enter a name.',
       ];
   }
   ```
3. **Add custom `attributes()` method** as a fallback for any messages not explicitly defined:
   ```php
   public function attributes(): array
   {
       return [
           'account_id' => 'account',
           'category_id' => 'category',
           'transaction_date' => 'date',
       ];
   }
   ```
4. **Check frontend error display** — Ensure `resources/js/` form components render error messages properly from Inertia's `errors` prop.

### Files to Modify
- All files in `app/Http/Requests/` — Add `messages()` and `attributes()` methods
- Key files: `StoreTransactionRequest.php`, `UpdateTransactionRequest.php`, `StoreBillRequest.php`, `StoreLedgerRequest.php`, etc.

### Testing
- Feature test: submit invalid data to each endpoint, assert human-friendly error messages
- Extend `FormRequestTest.php` with custom message assertions

---

## Task 1.2 — Fix "Add Transaction" Dead End

**Priority**: #4 — First action new users try, and it fails
**Effort**: Small

### Current State
- New user clicks "Add transaction" → modal opens → Account dropdown is empty
- Save produces raw error: `"The account id field is required."`
- No guidance on what to do

### Implementation Steps

1. **Add empty-account guard in modal** — In `add-transaction-modal.tsx`, check if accounts list is empty. If so, show friendly message: `"You need at least one account first. Let's create one!"` with a button linking to account creation.
2. **Conditional dashboard button** — In `dashboard.tsx`, if no accounts exist, change "Add transaction" button text to "Set up your first account" and link to accounts page.
3. **Pass account count to frontend** — Ensure the dashboard controller passes `accounts_count` or the accounts list to the Inertia page.

### Files to Modify
- `resources/js/components/add-transaction-modal.tsx` — Add empty state check
- `resources/js/pages/dashboard.tsx` — Conditional button
- `app/Http/Controllers/DashboardController.php` — Ensure accounts are passed

### Testing
- Feature test: new user with no accounts visits dashboard, verify correct button text
- Feature test: open transaction modal with no accounts, verify friendly message

---

## Task 1.11 — Bill Pay Confirmation Dialog

**Priority**: #5 — One-click irreversible action with no undo
**Effort**: Small

### Current State
- Clicking "Pay" immediately creates a transaction — no confirmation
- No indication of which account is charged
- Bill vanishes from upcoming list silently
- Transaction backdated to due date, not today

### Implementation Steps

1. **Create `PayBillDialog` component** — A confirmation modal showing:
   - Title: `"Pay {bill_name}?"`
   - Amount display
   - Account dropdown (default to bill's configured account)
   - Date picker (default to today)
   - "Pay Now" / "Cancel" buttons
2. **Wire into bill pay action** — Replace the direct `router.post` call with opening the dialog first
3. **Update bill pay endpoint** — Accept optional `account_id` and `payment_date` overrides from the dialog
4. **Show toast on success** — `"{bill_name} paid — RM {amount} from {account}"` (builds on Task 1.3)

### Files to Modify
- `resources/js/components/pay-bill-dialog.tsx` — New component (or inline in existing bill components)
- `resources/js/pages/dashboard.tsx` — Replace direct pay action
- `resources/js/pages/ledgers/bills/*.tsx` — Replace direct pay action
- `app/Http/Controllers/Ledger/BillController.php` — Accept payment overrides
- `app/Services/BillService.php` — Handle payment date/account overrides

### Testing
- Feature test: pay bill with custom date and account, verify transaction created correctly
- Manual: confirm dialog appears, verify toast after payment

---

## Task 1.6 — Fix All Product Branding

**Priority**: #6 — Product looks like a framework demo
**Effort**: Small

### Current State
- Browser tabs say `"Page Name - Laravel"`
- Favicon is Laravel logo
- "Feenans" appears nowhere in the UI
- Auth pages are generic

### Implementation Steps

1. **Fix page titles** — In `app/Http/Middleware/HandleInertiaRequests.php`, change the app name default. Also check `config/app.php` `name` value — set to `"Feenans"`.
2. **Create favicon** — Design a simple "F" icon in the app's accent color. Place in `public/favicon.ico` and `public/favicon.svg`.
3. **Update `<head>` meta** — In the main Blade template (likely `resources/views/app.blade.php`), update `<title>` pattern to `"{Page} — Feenans"`.
4. **Add branding to sidebar** — In `resources/js/components/app-sidebar.tsx`, add "Feenans" wordmark above the workspace name.
5. **Brand auth pages** — In `resources/js/layouts/auth-layout.tsx`, add Feenans logo/wordmark above the form.

### Files to Modify
- `config/app.php` — Set `'name' => 'Feenans'`
- `resources/views/app.blade.php` — Update title template
- `public/favicon.ico`, `public/favicon.svg` — New favicon
- `resources/js/components/app-sidebar.tsx` — Add wordmark
- `resources/js/layouts/auth-layout.tsx` — Add branding
- `app/Http/Middleware/HandleInertiaRequests.php` — Verify title sharing

### Testing
- Manual: verify all page tabs show "— Feenans" suffix
- Manual: verify favicon appears
- Manual: verify sidebar and auth pages show branding

---

## Task 1.10 — Fix Placeholder Pretending to Be a Value

**Priority**: #7 — Users can't get past workspace creation
**Effort**: Tiny

### Current State
- Workspace creation "Name" field shows "Personal" in grey (placeholder)
- Looks pre-filled but isn't — users try to submit without typing
- Browser validation blocks submission with no clear explanation

### Implementation Steps

1. **Set default value** — In the ledger creation form (likely in `resources/js/pages/ledgers/` or `resources/js/pages/onboarding/`), set `defaultValue="My Finances"` instead of using a placeholder.
2. **Alternatively** — Change placeholder to `"e.g., My Finances"` to make it clearly a suggestion.

### Files to Modify
- `resources/js/pages/ledgers/create.tsx` (or equivalent form component)
- `app/Http/Controllers/LedgerController.php` — Optionally set server-side default

### Testing
- Manual: visit workspace creation, verify field has actual value or clearly-styled placeholder

---

## Task 1.12 — Enrich Default Categories for New Users

**Priority**: #8 — New user experience feels barren
**Effort**: Medium

### Current State
- New workspace gets only ~5 categories (Food, Transport, Bills, Salary, Bonus)
- No subcategories, no colors
- Demo user has 10+ parents with 40+ subcategories, all color-coded

### Implementation Steps

1. **Find the seeder/setup logic** — Check `app/Services/LedgerSetupService.php` for the method that creates default categories when a new ledger is created.
2. **Expand the category set** — Add the full Malaysian-relevant category tree:
   - **Expense**: Food & Drinks (Groceries, Dining Out, Coffee & Tea, Snacks), Transport (Grab, Fuel, Toll, Parking, Public Transport), Shopping (Clothing, Electronics, Online Shopping, Home Goods), Utilities (Electricity, Water, Internet, Mobile Plan), Entertainment (Streaming, Movies, Games, Events), Health (Pharmacy, Doctor/Clinic, Gym, Supplements), Personal Care (Haircut, Skincare, Toiletries), Education (Books, Courses, Tuition), Home (Rent, Maintenance, Furniture)
   - **Income**: Salary, Bonus, Freelance, Dividends, Other Income
3. **Assign distinct colors** — Each parent category gets a unique hex color.
4. **Update the seeder** — If there's a `database/seeders/` file for demo data, update it too.

### Files to Modify
- `app/Services/LedgerSetupService.php` — Expand default category creation
- `database/seeders/` — Update demo seeder if applicable

### Testing
- Feature test: create new ledger, assert correct number of categories with colors
- Feature test: verify parent-child relationships are correct

---

## Task 1.7 — Currency Searchable Dropdown

**Priority**: #9 — Permanent data quality issue
**Effort**: Small

### Current State
- Currency field is a raw text input — users can type anything
- No validation until submission
- Once saved, currency is read-only in Settings — wrong value is permanent

### Implementation Steps

1. **Create currency data file** — `resources/js/lib/currencies.ts` with ISO 4217 currency list: `[{ code: 'MYR', name: 'Malaysian Ringgit', symbol: 'RM' }, ...]`
2. **Build searchable dropdown** — Use `cmdk` (already installed) or Radix Select with search. Default to MYR.
3. **Replace text input** — In ledger creation and onboarding forms, swap the text input for the searchable dropdown.
4. **Add backend validation** — In `StoreLedgerRequest.php`, validate `currency` against the ISO 4217 list using `Rule::in(...)`.
5. **Settings currency change** — Allow currency change only if zero transactions exist. Show warning in settings.

### Files to Modify
- `resources/js/lib/currencies.ts` — New data file
- `resources/js/pages/ledgers/create.tsx` or onboarding form — Replace input
- `app/Http/Requests/StoreLedgerRequest.php` — Add currency validation
- `resources/js/pages/settings/*.tsx` — Conditional currency edit with warning

### Testing
- Feature test: submit invalid currency code, assert validation error
- Feature test: submit valid currency code, assert accepted
- Feature test: attempt currency change with existing transactions, assert blocked

---

## Task 1.8 — Replace "Ledger" Terminology Everywhere

**Priority**: #10 — Confuses 95% of users
**Effort**: Small

### Current State
- "Ledger" appears in creation page, settings ("Delete this ledger"), sidebar, breadcrumbs
- Non-accounting users don't understand it

### Implementation Steps

1. **Find all user-facing "ledger" strings** — Search across `resources/js/` and `resources/views/` for case-insensitive "ledger".
2. **Replace with "workspace"**:
   - "Create ledger" → "Create your workspace"
   - "Delete this ledger" → "Delete this workspace"
   - Settings title → "Workspace Settings"
   - Breadcrumbs, navigation, etc.
3. **Backend strings** — Check `app/Http/` controllers and form requests for validation messages containing "ledger".
4. **Keep internal code as-is** — Model names, database tables, route names stay as `ledger` internally — only user-facing strings change.

### Files to Modify
- `resources/js/pages/ledgers/*.tsx` — All ledger-related pages
- `resources/js/components/ledger-switcher.tsx` — Update labels
- `resources/js/components/app-sidebar.tsx` — Update navigation text
- `app/Http/Requests/StoreLedgerRequest.php` — Update validation messages
- `resources/js/pages/settings/*.tsx` — Update labels

### Testing
- Grep for user-facing "ledger" strings — should find zero after changes
- Manual: walk through all pages, confirm "workspace" used consistently

---

## Task 1.13 — Design Proper Empty States

**Priority**: #11 — Dashboard looks broken when empty
**Effort**: Medium

### Current State
- Empty dashboard shows three "RM 0.00" cards, empty chart with Y-axis 0-4, "No upcoming bills", "No accounts yet"
- Looks broken, not empty

### Implementation Steps

1. **Create `EmptyState` component** — Reusable component in `resources/js/components/ui/empty-state.tsx`:
   ```tsx
   type EmptyStateProps = {
     icon: ReactNode;
     title: string;
     description: string;
     action?: { label: string; href: string };
   };
   ```
2. **Dashboard empty state** — Replace empty chart with getting-started checklist:
   - ☐ Create your first account
   - ☐ Add your first transaction
   - ☐ Set up a recurring bill
   - ☐ Create a budget
   - Each links to relevant action. Auto-dismiss after 5+ transactions.
3. **Page-specific empty states**:
   - Transactions: "No transactions yet. Start tracking your spending!" + Add button
   - Accounts: "Add your bank accounts and wallets to start tracking." + New Account button
   - Bills: "Set up recurring bills to never miss a payment." + New Bill button
   - Payees: "Payees will appear here as you create transactions."
   - Reports: "Add some transactions first to see your spending insights."
4. **Pass counts from backend** — Dashboard controller should pass `transaction_count`, `account_count`, `bill_count`, `budget_count` for checklist state.

### Files to Modify
- `resources/js/components/ui/empty-state.tsx` — New reusable component
- `resources/js/pages/dashboard.tsx` — Getting-started checklist
- `resources/js/pages/ledgers/transactions/index.tsx` — Empty state
- `resources/js/pages/ledgers/accounts/index.tsx` — Empty state
- `resources/js/pages/ledgers/bills/index.tsx` — Empty state
- `resources/js/pages/ledgers/payees/index.tsx` — Empty state
- `resources/js/pages/ledgers/reports/index.tsx` — Empty state
- `app/Http/Controllers/DashboardController.php` — Pass entity counts

### Testing
- Feature test: new user dashboard returns correct empty state data
- Manual: verify each page shows purposeful empty state

---

## Task 1.1 — Onboarding Wizard

**Priority**: #12 — Biggest FTUE gap, but large effort
**Effort**: Large

### Current State
- After registration → "Create ledger" page with confusing fields
- No welcome message, no guidance, no progress indicator
- `app/Http/Controllers/OnboardingController.php` and `resources/js/pages/onboarding/` exist (skeleton)
- `app/Http/Middleware/EnsureOnboardingComplete.php` exists
- User model has onboarding columns

### Implementation Steps

1. **Define onboarding steps** — 6 steps stored as enum/constants:
   - `welcome` → `name_workspace` → `add_accounts` → `choose_categories` → `first_transaction` → `celebration`
2. **Create step components** — Each step as a separate component in `resources/js/pages/onboarding/`:
   - `welcome.tsx` — Welcome screen with progress indicator
   - `workspace.tsx` — Name + currency dropdown (from Task 1.7) + cycle start day with explanation
   - `accounts.tsx` — Quick-select Malaysian banks (Maybank, CIMB, RHB, Public Bank) + Cash, TNG, GrabPay. Each auto-fills name and type. Add initial balance.
   - `categories.tsx` — Preset selection: "Malaysian Starter Pack" (builds on Task 1.12), "Minimal", "Start Empty". Show preview.
   - `first-transaction.tsx` — Optional pre-filled hint transaction. "Skip" button.
   - `celebration.tsx` — "You're all set!" → redirect to dashboard
3. **Backend onboarding flow** — In `OnboardingController.php`:
   - `show(step)` — Return the appropriate step page
   - `store(step)` — Save step data, advance to next step
   - Track progress in user's `onboarding_step` column
   - Allow resuming from where the user left off
4. **Middleware** — `EnsureOnboardingComplete` redirects incomplete users to their current step
5. **LedgerSetupService integration** — Create workspace, accounts, categories based on onboarding choices

### Files to Modify
- `resources/js/pages/onboarding/*.tsx` — Step components (new/expanded)
- `app/Http/Controllers/OnboardingController.php` — Step logic
- `app/Services/LedgerSetupService.php` — Accept onboarding config
- `app/Http/Middleware/EnsureOnboardingComplete.php` — Verify/update logic
- `routes/web.php` — Onboarding routes
- Database migration if additional onboarding columns needed

### Testing
- Feature test: complete each onboarding step, verify data saved correctly
- Feature test: incomplete onboarding redirects to correct step
- Feature test: full wizard completion creates workspace with chosen settings

---

## Task 1.5 — Replace the Landing Page

**Priority**: #13 — First impression, but less urgent than functional bugs
**Effort**: Medium

### Current State
- Root URL shows default Laravel welcome page
- Laravel logo, "Read the Documentation" link, "Deploy now" button
- Zero indication this is Feenans

### Implementation Steps

1. **Design landing page** — In `resources/js/pages/welcome.tsx`:
   - Product name "Feenans" with wordmark
   - Tagline: "Track your money, your way."
   - 3-4 feature highlights with Lucide icons (multi-account, recurring bills, visual reports, budgets)
   - Prominent "Get Started" button → `/register`
   - "Already have an account? Log in" link → `/login`
   - Dark theme matching the app aesthetic
2. **Update route** — Ensure `routes/web.php` renders the Inertia `welcome` page for unauthenticated users at `/`
3. **Redirect authenticated users** — If logged in, redirect from `/` to dashboard

### Files to Modify
- `resources/js/pages/welcome.tsx` — Complete redesign
- `routes/web.php` — Verify routing logic
- Possibly `resources/views/app.blade.php` — Ensure meta tags are correct

### Testing
- Feature test: unauthenticated user visits `/`, sees landing page
- Feature test: authenticated user visits `/`, redirected to dashboard
- Manual: visual check of landing page design

---

## Implementation Order Summary

| Order | Task | Effort | Dependencies |
|-------|------|--------|-------------|
| 1 | 1.3 Toast notifications | Small | None |
| 2 | 1.4 Fix edit date bug | Tiny | None |
| 3 | 1.9 Humanize errors | Small | None |
| 4 | 1.2 Fix Add Transaction dead end | Small | None |
| 5 | 1.11 Bill pay confirmation | Small | 1.3 (toast) |
| 6 | 1.6 Fix branding | Small | None |
| 7 | 1.10 Fix placeholder trap | Tiny | None |
| 8 | 1.12 Enrich default categories | Medium | None |
| 9 | 1.7 Currency dropdown | Small | None |
| 10 | 1.8 Kill "ledger" jargon | Small | None |
| 11 | 1.13 Empty states | Medium | None |
| 12 | 1.1 Onboarding wizard | Large | 1.7, 1.8, 1.12 |
| 13 | 1.5 Landing page | Medium | 1.6 (branding) |

**Total estimated tasks**: 13
**Critical path**: Tasks 1-7 can be parallelized. Task 1.1 (onboarding) depends on 1.7, 1.8, 1.12 being done first.
