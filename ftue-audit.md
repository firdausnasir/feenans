# Feenans FTUE Audit — First-Time User Experience (Signup → First Transaction)

> Perspective: Product Manager with 10 years of experience building consumer fintech products.
> Audit method: Full walkthrough as a brand-new user — signup, ledger creation, account setup, first transaction.
> Date: 14 March 2026

---

## The Journey I Walked

1. Landed on `localhost:8000` (welcome page)
2. Clicked "Register"
3. Filled out signup form (name, email, password, confirm)
4. Submitted → landed on "Create ledger" page
5. Created a ledger ("My Wallet", MYR)
6. Landed on empty dashboard
7. Clicked "Add transaction" → hit a dead end (no accounts exist)
8. Navigated to Accounts → created "Maybank Savings" with RM 5,000
9. Returned to dashboard → clicked "Add transaction" again
10. Created first expense (RM 15.50, Starbucks coffee, Food category)
11. Explored Categories, Budgets, Import, Reports

**Time to first successful transaction: ~4 minutes with confusion.**
**A non-technical user would likely take 8-10 minutes or abandon.**

---

## THE GOOD

### 1. Dashboard layout is well-designed
The dashboard packs a lot of information density without feeling overwhelming. Income/Expense/Net cards at top, billing cycle display, trend chart, accounts widget, top categories, and recent transactions — all in one scrollable view. For an existing user with data, this is a strong at-a-glance summary.

### 2. Real-time reactivity after transaction creation
When I saved a transaction, the dashboard behind the modal updated instantly — Expense card changed from RM 0.00 to RM 15.50, the account balance recalculated, the trend chart populated, and the categories bar appeared. No page reload needed. This feels modern and responsive.

### 3. Transaction modal is convenient
The "Add transaction" modal lets you log expenses without leaving whatever page you are on. The expense/income/transfer tab design is clean and intuitive. Having the modal overlay the dashboard means quick entry without context-switching.

### 4. Split transaction toggle exists
The "Split transaction — Break this transaction into multiple category lines" toggle in the transaction form is a power-user feature that shows product maturity. Most finance apps either never add this or bolt it on awkwardly.

### 5. Account types are sensible defaults
The seeded account types (Cash, Bank, Savings, Credit Card, E-Wallet, Investment, Loan) cover the common Malaysian financial landscape well. No setup needed for types.

### 6. Import feature with 3-step wizard
The CSV import page (Upload → Map Columns → Preview & Confirm) is a well-designed wizard. The drag-and-drop zone with file type/size hints is professional. This reduces the barrier for migrating from another tool or bank statements.

### 7. Budgets page has a good empty state
"No budgets yet. Create a budget to track your spending limits." with a centered CTA button. This is the correct empty state pattern — explains what the feature does and gives a clear next action.

### 8. Cycle navigation on dashboard
The `< Cycle: 1 Mar 2026 – 31 Mar 2026 >` with arrow navigation lets users browse historical periods. This is a small but critical feature that many finance apps miss.

### 9. Breadcrumb navigation
Every page has breadcrumbs (My Wallet > Accounts > Create account). This gives users spatial awareness and an easy way to go back.

### 10. Accounts page shows Net Worth
The Total Assets / Total Liabilities / Net Worth cards on the accounts page are exactly what a finance-conscious user wants to see at a glance.

---

## THE BAD

### 1. No onboarding flow whatsoever
After signup, the user lands on "Create ledger" with zero context. There is no welcome screen, no guided setup wizard, no explanation of what a "ledger" is, no prompts to create accounts or categories. The codebase has an onboarding controller and model fields (onboarding_step, onboarding_data) but the flow is either broken or bypassed for new users. This is the single biggest gap in the product.

**What should happen:** A 3-4 step wizard after signup: "Welcome to Feenans! Let's set up your finances." → Step 1: Name your space (ledger) → Step 2: Add your bank accounts → Step 3: Pick your expense categories → Step 4: Log your first transaction. Show a progress bar. Make it feel guided and safe.

### 2. Landing page is the default Laravel welcome page
The first thing a visitor sees is the Laravel logo, "Read the Documentation", "Watch video tutorials at Laracasts", and a "Deploy now" button. There is zero product branding, zero value proposition, zero explanation of what Feenans does. A visitor who lands here has no idea this is a finance tracker.

**What should happen:** A proper landing page with: product name/logo, a one-liner ("Track your money, your way"), 2-3 feature highlights, social proof or screenshot, and a prominent "Get Started Free" CTA.

### 3. "Create ledger" page uses jargon and is too minimal
The term "ledger" means nothing to 95% of users. The form has only two fields (name and currency code) with no explanation. "Currency code" is a raw text input where you type "MYR" — not a searchable dropdown with country flags. There is no option to choose preset categories during ledger creation. A user who types "RM" or "Ringgit" instead of "MYR" would get an error or wrong behavior.

**What should happen:** Replace "ledger" with "workspace" or just "your finances". Make currency a searchable dropdown ("Malaysian Ringgit (MYR)"). Add a checkbox or step: "Pre-fill common expense categories?" with a preview of what gets created.

### 4. "Add transaction" button is accessible before accounts exist — dead end
A new user's most obvious first action on the empty dashboard is clicking "Add transaction". The modal opens, but the Account dropdown says "Select account" with zero options. Submitting produces a raw validation error: "The account id field is required." This is a frustrating dead end that teaches the user the system is broken, not that they need to create an account first.

**What should happen:** Either (a) disable "Add transaction" when no accounts exist and show a banner: "Create your first account to start tracking" with a CTA, or (b) detect zero accounts in the modal and show an inline message with a link: "You don't have any accounts yet. Create one first →", or (c) allow inline account creation from within the transaction modal.

### 5. Validation error messages are raw/technical
"The account id field is required." is a developer-facing message, not a user-facing one. This is the Laravel default validation message format. Users do not know what "account id field" means.

**What should happen:** Custom, human-readable error messages. "Please select an account" instead of "The account id field is required." Review all form request validation messages across the app.

### 6. No success confirmation after saving a transaction
After clicking "Save transaction", the modal stays open with the same data filled in. There is no toast notification, no visual flash, no "Transaction saved!" message, no form reset. The only clue it worked is that the dashboard numbers changed behind the modal — but the user is looking at the modal, not the dashboard. Many users would click "Save transaction" again, creating a duplicate.

**What should happen:** After successful save: show a success toast ("Expense saved — RM 15.50"), reset the form fields, and optionally auto-close the modal (or keep it open with cleared fields for rapid entry, but communicate that clearly).

### 7. Seeded categories are embarrassingly sparse
A new ledger gets only 5 categories total: Food, Transport, Bills (expense) and Salary, Bonus (income). No subcategories, no colors, no icons. The demo user's ledger has 10+ parent categories with 40+ subcategories, all color-coded. A new user sees a bare, colorless list and has to manually create everything.

**What should happen:** Offer a "Starter Pack" of 15-20 common categories with subcategories during onboarding. For Malaysian users: Food & Drinks (Groceries, Dining Out, Coffee), Transport (Grab, Fuel, Toll, Parking), Shopping (Online, Clothing), Utilities (Electricity, Water, Internet, Mobile), Entertainment (Streaming, Movies), Health (Pharmacy, Doctor), etc. Pre-assign colors.

### 8. No success feedback after creating an account
After creating the "Maybank Savings" account, the app redirected to the accounts list. There was no toast notification or success message confirming the account was created. The user has to visually scan the page to confirm it worked.

### 9. Categories page has no edit or delete actions visible
Categories are listed as flat text with grey dots. There are no hover actions, no context menus, no edit/delete buttons. The user cannot rename, recolor, or remove a category from this page without knowing to look elsewhere.

### 10. Dashboard empty state is just zeros everywhere
When a new user first sees the dashboard, it is a wall of "RM 0.00" across three cards, empty charts with meaningless Y-axis labels (1, 2, 3, 4), "No upcoming bills", "No accounts yet", "No expenses this cycle." There are no helpful prompts or guided actions. The empty chart showing a scale of 0-4 when there is no data looks broken rather than intentionally empty.

**What should happen:** Replace the empty chart with a placeholder illustration or message: "Your spending trends will appear here once you log transactions." Add a prominent "Get started" checklist: ☐ Create an account, ☐ Add your first transaction, ☐ Set up a recurring bill.

---

## THE UGLY

### 1. The Ledger name placeholder pretends to be a value
On the "Create ledger" page, the "Ledger name" field shows "Personal" in grey — this looks like a pre-filled default value but is actually just a placeholder. Clicking "Create ledger" without typing anything triggers "Please fill out this field" browser validation on the name. This is a UI anti-pattern that will trick users into thinking the field is already filled. Either make it a real default value or make the placeholder clearly look empty (e.g., "e.g., Personal Finances").

### 2. Currency is a free-text input, not a dropdown
The "Currency code" field is a raw text input showing "MYR". A user could type "USD", "usd", "dollars", "RM", "Ringgit", or anything. There is no validation feedback until submission. Most finance apps use a searchable dropdown with currency name + code + symbol. This is the kind of input that will produce silent data corruption (wrong currency code) that is hard to fix later since the Settings page makes currency read-only.

### 3. The modal does not close or reset after saving — duplicate transaction trap
This is the most dangerous UX issue in the app. After a successful save, the modal retains all entered data (amount, category, description, date) and shows no success indicator. A user who is unsure whether the save worked will naturally click "Save transaction" again — creating an exact duplicate. There is no duplicate detection or "are you sure?" guard. The user will only discover the duplicate later when reviewing transactions.

### 4. Zero guardrails on the happy path
The app assumes users know the correct sequence: create ledger → create account → add transaction. But there are zero guardrails enforcing or even suggesting this order. Every feature (transactions, bills, budgets, reports) is accessible from the sidebar immediately, and most of them are dead ends until accounts and categories exist. The sidebar shows 10 navigation items for a user who has completed 0 setup steps.

**What should happen:** Progressive disclosure. Lock or visually dim sidebar items that require prerequisites. Show a setup progress indicator. Guide the user through the critical path before exposing the full UI.

### 5. Page title says "Laravel" — no product identity
The browser tab says "My Wallet - Laravel" or "Register - Laravel". The product is called Feenans, but the word "Feenans" appears nowhere in the UI. Every page title includes "- Laravel" which is a framework name, not a product name. The favicon is the Laravel logo. From a branding perspective, this product has no identity.

---

## Improvement Recommendations (Prioritized)

### Must-Fix Before Any User Testing

1. **Build a real onboarding wizard.** This is priority zero. The flow should be: signup → welcome → name your workspace → add first account → choose category preset → log first transaction → celebration screen. Every step should feel guided and achievable. Show a progress bar.

2. **Fix the "Add transaction" dead end.** When no accounts exist, either block the action with a helpful message or allow inline account creation. Never show a user an empty dropdown with a raw error.

3. **Add success feedback everywhere.** Toast notifications after every create/update/delete action. The modal should either close on success or visibly reset with a confirmation message.

4. **Replace the landing page.** Build a proper product landing page. It does not need to be fancy — a hero section with the product name, one sentence, one screenshot, and a "Get Started" button.

5. **Fix the currency input.** Replace the text input with a searchable dropdown of valid currency codes. This prevents silent data corruption.

### Should-Fix Before Launch

6. **Rename "ledger" to something human.** "Workspace", "Space", "My Finances", or just remove the concept entirely for single-user mode.

7. **Enrich the default categories.** Ship with 15-20 categories + subcategories, pre-colored. Offer a "Malaysian Starter" preset during onboarding.

8. **Humanize all validation messages.** Audit every FormRequest class and replace Laravel's default "The {field} field is required" with user-friendly messages.

9. **Design proper empty states for all pages.** Each empty state should explain what the feature does, show a relevant illustration or icon, and provide a single clear CTA. The Budgets page does this right — replicate that pattern everywhere.

10. **Add product branding.** Replace "- Laravel" in page titles with "- Feenans". Add a logo/wordmark to the sidebar header and auth pages. Set a proper favicon.

### Nice-to-Have for Delight

11. **Add a "Quick Setup" checklist to the dashboard.** Show it only for new users with < 5 transactions. ☐ Create an account ☐ Log your first expense ☐ Set up a recurring bill ☐ Create a budget. Dismiss once complete.

12. **Celebrate the first transaction.** Show a confetti animation or a friendly message: "Your first transaction! You're on your way to financial clarity." Small moments of delight drive retention.

13. **Add keyboard shortcuts.** `N` for new transaction, `Esc` to close modals. Power users will love this.

14. **Add a "Stay open for rapid entry" toggle** to the transaction modal. When off (default), the modal closes after save. When on, it stays open and resets. This serves both casual and power users.

15. **Show an example/sample data option** during onboarding: "Want to see how Feenans looks with data? Load sample transactions." Let users explore a populated dashboard before committing to manual entry.

---

## Summary Scorecard

| Area | Score (1-10) | Notes |
|------|-------------|-------|
| First impression (landing page) | 2/10 | Default Laravel page, no product identity |
| Signup flow | 6/10 | Functional but generic, no branding |
| Onboarding | 1/10 | Effectively non-existent |
| Time to value | 3/10 | ~4 min with confusion, dead ends before first transaction |
| Empty states | 4/10 | Budgets good, everything else is just zeros or missing |
| Transaction creation UX | 5/10 | Form is good but no feedback, duplicate trap |
| Information architecture | 7/10 | Sidebar is logical, breadcrumbs work, page layouts are clean |
| Visual design | 7/10 | Dark theme looks polished, cards and charts are well-styled |
| Error handling | 2/10 | Raw validation messages, no graceful fallbacks |
| Feature completeness | 7/10 | Accounts, transactions, categories, bills, budgets, import, reports — breadth is solid |

**Overall FTUE Score: 4.4 / 10**

The underlying product is surprisingly feature-rich (budgets, split transactions, CSV import, recurring bills, multi-account types). But the first-time experience actively hides this quality behind a wall of confusion, dead ends, and missing guidance. The app assumes its users already know what to do and in what order. Fix the onboarding, add feedback loops, and this product could easily score 7+.
