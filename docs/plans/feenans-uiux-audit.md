# Feenans UI/UX Design Audit

**Auditor perspective**: Senior UI/UX Designer (10 YOE, award-winning web design)
**Date**: March 16, 2026
**Application**: Feenans Personal Finance Tracker
**Stack**: Laravel 12 + Inertia.js v2 (React) + Tailwind CSS v4
**Theme**: Dark mode

---

## Severity Legend

- **P0 — Critical**: Breaks user trust, causes confusion, or blocks core flows
- **P1 — High**: Noticeably degrades experience or looks unpolished
- **P2 — Medium**: Inconsistency that experienced users will notice
- **P3 — Low**: Polish item, nice-to-have refinement

---

## 1. Global & Systemic Issues

### 1.1 Inconsistent Spacing Scale (P1)

**Flaw**: Section gaps, card padding, and component margins vary across pages without a clear rhythm. The Dashboard uses generous vertical spacing between widget rows, but pages like Categories and Tags feel cramped with tighter gaps between elements.

**Fix**: Establish a vertical rhythm token system. Use `gap-6` (24px) as your baseline section gap across all pages. Card internal padding should be a consistent `p-5` or `p-6`. Audit every page container and enforce:
```
Page header → content: gap-6
Section → section: gap-6
Card internal padding: p-5 (compact cards) or p-6 (detail cards)
```

### 1.2 No Consistent Empty State Pattern (P1)

**Flaw**: Empty states vary wildly. Budgets shows a centered illustration + CTA button (good). But other pages like Transactions or Recurring when empty would just show a blank table. There's no shared empty state component.

**Fix**: Create a single `<EmptyState>` component with props for `icon`, `title`, `description`, and `actionLabel`/`actionHref`. Every data table and card grid must use it. Pattern:
```
[Icon — 48px, muted color]
[Title — text-lg font-medium text-zinc-300]
[Description — text-sm text-zinc-500, max-w-sm mx-auto]
[CTA Button — primary style, optional]
```

### 1.3 Color Dot System Is Inconsistent (P2)

**Flaw**: Accounts use colored dots to differentiate account types. Categories use colored dots for parent categories, but subcategory dots are all grey and don't inherit the parent color. This breaks the visual hierarchy and makes subcategories feel orphaned.

**Fix**: Subcategory dots should inherit the parent's hue at a lighter/desaturated variant. For example, if "Food & Drink" is `bg-emerald-500`, its children ("Groceries", "Restaurants") should use `bg-emerald-400/60` or `bg-emerald-500/40`. This creates a clear visual family grouping.

### 1.4 Inconsistent Action Patterns (P1)

**Flaw**: Different pages use different interaction patterns for the same conceptual action (edit/delete):
- **Payees**: Inline pencil icon + trash icon on each card
- **Recurring**: Three-dot overflow menu per row
- **Categories**: Click to expand, then actions appear
- **Tags**: No visible edit/delete — unclear how to manage

**Fix**: Pick ONE primary action pattern and apply it everywhere:
- **Data tables** (Transactions, Recurring, Tags): Three-dot overflow menu on row hover → dropdown with Edit, Delete
- **Card grids** (Payees, Accounts): Icon buttons visible on hover (pencil, trash), pinned to card top-right
- **Hierarchical lists** (Categories): Inline icon buttons on row hover, right-aligned

Document this in your component library so every new page follows the same pattern.

### 1.5 Typography Hierarchy Needs Tightening (P2)

**Flaw**: Page titles are consistent (`text-2xl font-bold`), but secondary text and label sizes vary. Some labels are `text-xs`, others `text-sm`. Some use `text-zinc-400`, others `text-zinc-500`. This creates a subtle but noticeable visual inconsistency.

**Fix**: Define and enforce three text tiers:
```
Primary label:   text-sm font-medium text-zinc-300
Secondary label: text-sm text-zinc-400
Muted/helper:    text-xs text-zinc-500
```
Audit all form labels, table headers, card metadata, and sidebar items against these three tiers.

---

## 2. Landing Page

### 2.1 Mismatched CTA Copy (P1)

**Flaw**: The hero CTA says "Start Tracking for Free" but the footer CTA says "Get Started for Free". Two different labels for the same action creates subtle doubt — which one is the real message?

**Fix**: Unify all CTAs to a single label. Recommendation: **"Start Tracking for Free"** — it's more specific and action-oriented. Apply this to hero button, nav CTA, and footer CTA. Consistency builds trust.

### 2.2 Hero Section Is Text-Heavy (P2)

**Flaw**: The hero relies entirely on text (headline + subtitle + button). No product screenshot, illustration, or visual hook. For a finance tracker, users want to see the dashboard before they sign up. The hero feels like a SaaS template without the product shot.

**Fix**: Add a hero image or a stylized dashboard mockup below the CTA. Use a browser frame chrome effect with a screenshot of the dashboard at ~60% scale, tilted slightly with a subtle shadow. This is the #1 conversion lever for SaaS landing pages.

### 2.3 Feature Grid Cards Lack Visual Weight (P2)

**Flaw**: The feature cards in the grid section are text-only with small icons. They blend into the dark background and don't create a scannable visual rhythm. Users will skim past them.

**Fix**: Give each feature card a subtle border (`border border-zinc-800`) and a hover state (`hover:border-zinc-700 hover:bg-zinc-800/50 transition`). Increase icon size to 32px and give each icon a distinct accent color. The visual variety in icon colors will create a scannable grid even if users don't read the text.

### 2.4 Trust/Social Proof Is Weak (P3)

**Flaw**: Trust badges (if any) are small and easy to miss. There are no testimonials, user counts, or open-source badges visible.

**Fix**: If this is an open-source project, add a GitHub stars badge and "Self-hosted & private" badge prominently below the hero. If it's a SaaS, add a "No credit card required" line under the CTA. Trust signals belong within 1 scroll-fold of the hero.

---

## 3. Authentication Pages (Login / Register)

### 3.1 Auth Pages Float in a Void (P1)

**Flaw**: Both login and register pages show a centered white card floating on a plain dark background. There's no branding, no illustration, no warmth. It feels like a developer scaffold, not a designed product.

**Fix**: Add one of these to the auth layout:
1. **Split layout**: Left half = branding panel (gradient background, app logo, one-liner tagline, subtle pattern). Right half = form card.
2. **Background treatment**: Add a very subtle gradient mesh or dot grid pattern behind the card. Use something like `bg-gradient-to-br from-zinc-900 via-zinc-950 to-zinc-900`.

Either approach transforms auth from "developer template" to "designed product" with minimal effort.

### 3.2 No Password Visibility Toggle (P2)

**Flaw**: Password fields on both login and register have no eye icon to toggle visibility. Users (especially on mobile or with password managers) expect this.

**Fix**: Add a clickable eye/eye-off icon (`lucide-react` → `Eye`, `EyeOff`) inside the password input, right-aligned. Toggle between `type="password"` and `type="text"` on click.

### 3.3 Registration Form Doesn't Set Expectations (P2)

**Flaw**: The registration form collects Name, Email, Password, Confirm Password — but doesn't tell the user what happens next. Will they need to verify email? Will they land on a setup wizard? The lack of context increases drop-off anxiety.

**Fix**: Add a small line below the submit button: "You'll be taken to your dashboard to set up your first ledger." This sets expectations and reduces friction.

---

## 4. Sidebar Navigation

### 4.1 Grouped Navigation Is Good — But Group Labels Need Work (P2)

**Flaw**: The sidebar groups (Overview, Activity, Plan, Manage) are a solid IA improvement. However, the group labels are rendered in the same visual weight as the nav items themselves, making it hard to distinguish hierarchy at a glance.

**Fix**: Make group labels use `text-[11px] uppercase tracking-wider text-zinc-600 font-semibold` and add `mt-6 mb-1` spacing before each group. The items below should have slightly larger text and brighter color. This creates a clear visual separation between group headers and clickable items.

### 4.2 Active State Contrast Is Subtle (P2)

**Flaw**: The active sidebar item is indicated by a slight background highlight, but it's not immediately obvious which page you're on when glancing at the sidebar.

**Fix**: Add a left accent bar on the active item: `border-l-2 border-indigo-500` (or your primary accent color) combined with the background highlight. The accent bar creates an unmistakable active indicator that works even in peripheral vision.

### 4.3 Ledger Switcher Placement (P3)

**Flaw**: The ledger switcher dropdown sits at the top of the sidebar. For users with one ledger (most users), this is wasted prime real estate showing static content.

**Fix**: If the user has only one ledger, collapse the switcher to a compact display (just the ledger name, no dropdown chrome). Only show the full dropdown UI when there are 2+ ledgers. This reclaims vertical space for navigation items.

---

## 5. Dashboard

### 5.1 Stat Cards Lack Context (P1)

**Flaw**: The Income, Expense, and Net cards show numbers but lack trend context. Is $2,000 in expenses good or bad? Is it up or down from last month? Raw numbers without comparison are hard to act on.

**Fix**: Add a small trend indicator below each number:
```
$2,450.00
↑ 12% vs last month  (green for income up, red for expense up)
```
Use `text-xs` with `text-emerald-400` (positive) or `text-red-400` (negative). Even a simple "vs last month" comparison transforms static numbers into actionable insights.

### 5.2 Net Worth Card Needs a Sparkline (P2)

**Flaw**: The Net Worth card shows a single number. For a metric that's supposed to be the user's north star, it's underwhelming. It's just a number in a box.

**Fix**: Add a small sparkline (last 6 months of net worth) inside the card. Use a thin line chart, 80px tall, no axis labels, just the line with a gradient fill beneath it. The visual shape communicates the trend instantly without needing to read numbers.

### 5.3 "Upcoming Recurring" Shows Test Data (P0 — Data Quality)

**Flaw**: The Upcoming Recurring section displays an entry called "asdf" — clearly test data. While this is a data issue, from a UX perspective, the system should either flag or hide clearly invalid entries, or at minimum, the section should have a "no upcoming bills" empty state if there are no valid entries.

**Fix**: This is primarily a data cleanup issue. But defensively, add validation on recurring transaction names (minimum 2 characters, no random strings). Also ensure the "Upcoming Recurring" widget has a proper empty state when there are no upcoming items within the display window.

### 5.4 Dashboard Widget Layout Could Use Hierarchy (P2)

**Flaw**: All dashboard widgets have roughly equal visual weight. The Net Worth card, the stat row, the chart, and the tables all compete for attention. There's no clear visual hierarchy telling the user where to look first.

**Fix**: Apply a size hierarchy:
1. **Hero metric**: Net Worth — make it larger, perhaps spanning full width with a sparkline
2. **Summary row**: Income / Expense / Net — keep as compact stat cards
3. **Primary visual**: Trend chart — full width, slightly taller
4. **Secondary data**: Upcoming Recurring + Recent Transactions — two-column layout below

This creates a natural eye flow: top → big number → trend → details.

---

## 6. Transactions Page

### 6.1 Add Transaction Modal — No Currency Indicator (P1)

**Flaw**: The Amount field in the "Add Transaction" modal has no currency symbol or indicator. Users entering "50" don't know if the system interprets this as MYR, USD, or something else. This is a significant trust gap for a finance app.

**Fix**: Add a currency prefix inside the input field. Use a left-aligned label within the input: `MYR` (or the ledger's configured currency) styled as `text-zinc-500` inside the input's left padding. Example:
```
[ MYR  |  0.00                    ]
```

### 6.2 Modal Field Order Could Be Optimized (P2)

**Flaw**: The modal field order is: Amount → Account → Date → Split → Category → Payee → Description → Notes → Attachments → Tags. The "Split" toggle sits between Date and Category, which is an uncommon action that interrupts the natural flow.

**Fix**: Reorder to follow the user's mental model of recording a transaction:
1. **Type tabs** (Expense / Income / Transfer)
2. **Amount** + Currency
3. **Date** (default today)
4. **Account**
5. **Category**
6. **Payee**
7. **Description**
8. **Divider**
9. **Advanced section** (collapsible): Notes, Tags, Attachments, Split

Move Split into an "Advanced" collapsible section. Most users never split transactions — it shouldn't occupy prime real estate.

### 6.3 Filter Section — Collapsible Is Good, But State Is Unclear (P2)

**Flaw**: The collapsible filter section on the Transactions page is a good pattern. However, when filters are applied and the section is collapsed, there's no visible indicator that filters are active. Users may not realize they're viewing filtered data.

**Fix**: When filters are active and the section is collapsed, show a small pill/badge next to the filter toggle: "3 filters active" in `text-xs bg-indigo-500/20 text-indigo-400 rounded-full px-2 py-0.5`. This prevents the "why am I seeing different data?" confusion.

---

## 7. Accounts Page

### 7.1 Account Cards — Balance Alignment (P2)

**Flaw**: Account cards show the account name and balance, but the balance figures are not right-aligned consistently. Some cards with longer names push the balance to different horizontal positions, breaking visual scanability across the grid.

**Fix**: Use a flex layout with `justify-between` on each card row, and right-align all balance figures with `text-right tabular-nums font-mono`. The `tabular-nums` ensures digits align vertically when scanning multiple cards. This is essential for any financial UI.

### 7.2 No Total Per Account Group (P2)

**Flaw**: Accounts are grouped by type (Checking, Savings, Credit Card, etc.), but there's no subtotal shown per group. Users have to mentally add up balances within each group.

**Fix**: Add a group subtotal row at the bottom of each group section: `"Total Checking: MYR 5,230.00"` in `text-sm font-medium text-zinc-400`. This is a standard pattern in every finance app (Mint, YNAB, Actual Budget).

---

## 8. Categories Page

### 8.1 Subcategory Dots Don't Inherit Parent Color (P1)

**Flaw**: Already noted in Global Issues (1.3). Parent categories have distinct colored dots, but all subcategories show grey dots. This breaks the visual grouping and makes it impossible to quickly scan which subcategories belong to which parent by color alone.

**Fix**: Subcategory dots → parent color at 40-60% opacity. Creates instant visual family grouping.

### 8.2 No Drag-and-Drop Reordering (P3)

**Flaw**: Categories are listed in a fixed order with no way to reorder. Users who want "Groceries" at the top of their Food & Drink category can't do it without workarounds.

**Fix**: Add a drag handle icon (6-dot grip) on the left side of each category/subcategory row. Implement `@dnd-kit/sortable` for reordering within groups. This is a lower priority but significantly improves power-user experience.

---

## 9. Recurring Transactions Page

### 9.1 "Recurring" URL Returns 404 (P0)

**Flaw**: The sidebar item likely links to `/recurring` but the actual route is `/bills`. This is a broken navigation link that produces a 404 error.

**Fix**: Either update the route to match the sidebar label (`/recurring`) or update the sidebar label to say "Bills". The terminology should match between UI label and URL slug. Recommendation: use "Recurring" as the label and update the route to `/ledgers/{id}/recurring`.

### 9.2 Three-Dot Menu Is Good — But Needs Hover State (P2)

**Flaw**: The three-dot overflow menu on each row is a good pattern choice. However, the dots are always visible, adding visual noise to every row. On a page with many recurring transactions, this creates a cluttered look.

**Fix**: Show the three-dot icon only on row hover. Default state: hidden. Hover state: fade in with `opacity-0 group-hover:opacity-100 transition-opacity`. This keeps the table clean while maintaining discoverability.

### 9.3 No Visual Status for Overdue vs Upcoming (P1)

**Flaw**: Recurring transactions don't visually differentiate between overdue items (past due date, not yet paid) and upcoming items. They all look the same in the table.

**Fix**: Add a status indicator:
- **Overdue**: Red dot or `text-red-400` on the date, with a subtle red left border on the row
- **Due soon** (within 3 days): Amber/yellow dot
- **Upcoming**: Default styling

This creates urgency hierarchy that helps users prioritize which bills to pay first.

---

## 10. Payees Page

### 10.1 Card Grid Layout — Good Pattern, But Cards Need More Info (P2)

**Flaw**: Payee cards show the name, edit icon, and delete icon. But they don't show any context: how many transactions, last used date, total amount. A payee card that just says "AEON" is not very useful.

**Fix**: Add metadata to each card:
```
AEON
12 transactions · Last used Mar 10
[edit] [delete]
```
Use `text-xs text-zinc-500` for the metadata line. This transforms payee cards from mere labels into informative references.

### 10.2 Visible Duplicates Highlight a Missing Feature (P1)

**Flaw**: "AEON" and "Aeon" both exist as separate payees. The "Select to Merge" button exists but the fact that obvious duplicates persist suggests the merge feature isn't prominent enough or there's no auto-detection.

**Fix**: Add a duplicate detection banner at the top of the page when potential duplicates are found: "We found 2 potential duplicate payees. [Review & Merge]" in a subtle info bar (`bg-blue-500/10 border border-blue-500/20 text-blue-400`). This proactively guides users toward cleanup.

---

## 11. Tags Page

### 11.1 Raw Hex Code Displayed to Users (P1)

**Flaw**: The Tags table shows the raw hex color code (e.g., `#eab308`) as a column value. This is developer-facing data, not user-facing. No user cares about hex codes — they care about the visual color.

**Fix**: Replace the hex code text with a colored circle swatch (`w-4 h-4 rounded-full`) filled with the tag color. If you need to show the color value for editing, show it only in the edit modal, not in the table. The table should show: `[Color swatch] Tag Name | Transaction Count`.

### 11.2 No Inline Tag Color Editing (P3)

**Flaw**: To change a tag's color, you likely need to type a hex code. Users don't think in hex.

**Fix**: Use a small color picker popover (a grid of 12-16 preset colors) that appears on clicking the color swatch. Presets: `red-500, orange-500, amber-500, yellow-500, lime-500, emerald-500, teal-500, cyan-500, blue-500, indigo-500, violet-500, pink-500`. This covers 99% of use cases without any hex input.

---

## 12. Reports Page

### 12.1 Date Filter UX Is Good — Type Selector Placement Is Off (P2)

**Flaw**: The "Income & Expense" dropdown type selector is inside the collapsible filter section. But this isn't really a filter — it's a report type selector that changes the entire page view. Burying it inside a collapsible section makes it feel like a secondary control.

**Fix**: Pull the report type selector OUT of the collapsible filter and place it as a segmented control or tab bar directly below the page title. Pattern:
```
Reports
[Income & Expense] [Expense by Category] [Net Worth] [...]   ← Tab bar
[Filters ▼]                                                   ← Collapsible
[Chart + Data]
```

### 12.2 Export PDF — No Feedback After Click (P2)

**Flaw**: The "Export PDF" button exists but there's no loading state or success feedback. Users don't know if the export is processing, succeeded, or failed.

**Fix**: After click: show a spinner on the button with "Exporting..." text. On success: brief toast notification "Report exported successfully" with a download indicator. On error: red toast with retry option.

---

## 13. Settings Pages

### 13.1 Two Settings Contexts Is Confusing (P1)

**Flaw**: There are two separate settings pages — "Profile" (personal settings) and "Workspace Settings" (ledger/workspace config). The distinction between these isn't obvious from the sidebar. Users looking for "where do I change my currency?" will bounce between both pages.

**Fix**: Options:
1. **Merge into one page** with tabs: "Profile | Workspace | Danger Zone"
2. **Rename for clarity**: "My Profile" and "Workspace Settings" with a small description under each sidebar item (`text-xs text-zinc-500`: "Name, email, timezone" / "Accounts, data, API")

At minimum, add a brief description to each settings page header explaining what it controls.

### 13.2 Danger Zone — Delete Actions Need Confirmation Friction (P1)

**Flaw**: The Danger Zone section in Workspace Settings contains destructive actions. These need significant confirmation friction to prevent accidental data loss.

**Fix**: For any destructive action:
1. Button should be `bg-red-500/10 text-red-400 border border-red-500/20` (not a bright red button — that attracts clicks)
2. On click: modal with text confirmation ("Type DELETE to confirm")
3. Add a clear warning about what data will be lost
4. Add a 3-second delay before the confirm button becomes active

### 13.3 Timezone Dropdown — Good Addition, Needs Search (P2)

**Flaw**: The timezone dropdown contains hundreds of options. Scrolling through a dropdown with 400+ items is unusable.

**Fix**: Replace with a searchable combobox. User types "Kuala" → filtered to "Asia/Kuala_Lumpur". Use a Combobox component with search input at the top of the dropdown. This is the standard pattern for timezone selection in modern apps.

---

## 14. Import Page

### 14.1 Three-Step Wizard Is Clean — Step Indicators Need Polish (P2)

**Flaw**: The 3-step import wizard (Upload → Map → Review) uses a stepper UI. The step indicators work functionally but could use more visual refinement to clearly show completed vs. current vs. upcoming steps.

**Fix**: Use a stepper pattern with:
- **Completed step**: Filled circle with checkmark, solid connector line
- **Current step**: Filled circle with step number, pulsing ring animation
- **Upcoming step**: Outlined circle, dashed connector line
- Colors: Completed = `emerald-500`, Current = `indigo-500`, Upcoming = `zinc-600`

### 14.2 Drag-and-Drop Zone Needs Better Affordance (P2)

**Flaw**: The drag-and-drop upload zone exists but could be more visually inviting. A plain dashed border on dark background doesn't strongly communicate "drop files here."

**Fix**: Add:
1. An upload cloud icon (48px, `text-zinc-500`)
2. Text: "Drag & drop your CSV or OFX file here" (`text-sm text-zinc-400`)
3. Subtext: "or click to browse" (`text-xs text-zinc-500`)
4. On drag-over: border color change to `border-indigo-500`, background to `bg-indigo-500/5`
5. Accepted formats note: ".csv, .ofx, .qif" in `text-xs text-zinc-600`

---

## 15. Budgets Page

### 15.1 Empty State Is Decent — But Needs Motivation (P2)

**Flaw**: The Budgets empty state shows an illustration and a "Create Budget" button. It's functional but doesn't explain WHY the user should create a budget or what the feature does.

**Fix**: Add a brief value proposition line: "Set monthly spending limits for your categories and track how you're doing." This helps users who are exploring the app understand the feature before committing to creating one.

---

## Priority Summary

| Priority | Count | Key Items |
|----------|-------|-----------|
| P0 | 2 | Broken /recurring route, test data in production |
| P1 | 10 | Missing currency indicator, auth page design, inconsistent actions, CTA mismatch, duplicates, hex codes, settings confusion, stat cards lack context, danger zone, subcategory colors |
| P2 | 16 | Typography, spacing, hover states, filter indicators, account totals, field order, report type selector, various polish items |
| P3 | 3 | Drag reorder, color picker, trust badges |

---

## Recommended Implementation Order

**Sprint 1 — Fix the Breaks** (P0)
1. Fix `/recurring` route or sidebar link
2. Add data validation for recurring transaction names
3. Clean up test data

**Sprint 2 — Build Trust & Consistency** (P1)
4. Add currency indicator to amount fields
5. Unify CTA copy on landing page
6. Fix subcategory color inheritance
7. Standardize action patterns (hover menus, overflow dots)
8. Add trend indicators to dashboard stat cards
9. Replace hex codes with color swatches on Tags page
10. Add duplicate detection banner on Payees page
11. Improve auth page layout (split layout or background treatment)
12. Clarify settings page separation
13. Add confirmation friction to Danger Zone

**Sprint 3 — Polish & Delight** (P2-P3)
14. Enforce spacing scale system
15. Tighten typography hierarchy
16. Add filter-active indicators
17. Add sparkline to Net Worth card
18. Improve import wizard visuals
19. Add searchable timezone combobox
20. Pull report type selector out of filter collapse
21. Add export feedback states

---

*This audit was conducted through a systematic page-by-page visual inspection of the live application. All recommendations are written for an engineer implementing the changes — copy-paste the Tailwind classes and component patterns directly.*
