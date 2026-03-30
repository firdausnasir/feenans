# Transactions UI Phase 2 — Transfer Grouping & Infinite Scroll

**Date:** 2026-03-29  
**Branch:** feature/privacy-mode  
**Status:** Approved

---

## Overview

Three improvements to the transactions page:

1. **Transfer pair — collapsed/expandable row** (mobile + desktop): Replace the current two-row "TRANSFER" block with a single collapsed row that expands to reveal both legs.
2. **Desktop transfer grouping**: Apply the same transfer grouping and collapsed/expandable pattern to the desktop table (currently renders all transactions as flat rows with no transfer-specific treatment).
3. **Infinite scroll** (all viewports): Replace the standard prev/next pagination controls with scroll-triggered loading using Inertia v2's `WhenVisible` component.

---

## Section 1 — Transfer Pair Rendering (Shared, Both Viewports)

### Grouping

The existing `groupTransactionsForMobile()` helper (in `mobile-transaction-groups.ts`) already groups adjacent same-date transfer pairs. This logic will be shared with the desktop table — the same function is called before rendering both the mobile list and the desktop `<TableBody>`.

### Collapsed Row

A `transfer_pair` group renders as **one row** by default:

- **Icon:** `⇄` (`ArrowRightLeft`) before the title
- **Title / Account cell:** `From Account → To Account` — the outgoing side (negative amount) is "From", the incoming side is "To". Computed via the existing `resolveTransferCounterpart` helper.
- **Description:** shown if present on the outgoing transaction
- **Amount:** absolute value of the outgoing side, rendered in `text-foreground` (neutral, not red — a transfer is not a loss)
- **Checkbox, edit, duplicate, delete:** all target the **outgoing** (negative amount) transaction

### Expand Toggle

Below the main row content (in the same cell as the existing split expander), a small button:

- Label: `▼ 2 accounts` (collapsed) / `▲ 2 accounts` (expanded)
- Same visual style as the existing split expand button

### Expanded Sub-rows

When expanded, two sub-rows appear in a `bg-muted/40` rounded block:

- Each sub-row shows: account name + amount (same density as the existing split expansion)

### Standalone Transfer Rows

A transfer transaction whose counterpart is not in the current page's data (non-adjacent transfers, cross-page pairs, or cases where account filtering shows only one leg) renders as a **normal single row** with the `⇄` icon but **no** expand toggle.

**Determining "pairable":** The existing `groupTransactionsForMobile()` helper already handles this — it only groups a pair when both legs are adjacent in the same date group. Any transaction that ends up as a `kind: 'single'` item (including an unpaired transfer) renders as a regular row. On desktop, the same grouping is used. Actions (checkbox, edit, duplicate, delete) target the transaction as-is — whichever leg is visible.

---

## Section 2 — Desktop Table Transfer Grouping

The desktop table in `renderTransactionList` currently maps `txs.data` flat. After this change:

1. Call `groupTransactionsForMobile(txs.data)` to produce groups.
2. Flatten the groups back into renderable units: `single` items become one `<TableRow>`; `transfer_pair` items become a collapsed `<TableRow>` plus (when expanded) two additional sub-`<TableRow>` elements.

### Collapsed Transfer TableRow (desktop)

Columns:

- **Checkbox:** targets outgoing transaction
- **Date:** from the outgoing transaction
- **Account (md+):** `From Account → To Account` with `⇄` icon
- **Category (lg+):** empty (transfers have no category)
- **Payee:** empty
- **Description:** description if present
- **Amount:** absolute value, `text-foreground`
- **Files:** attachment count from outgoing side
- **Actions:** expand toggle chevron (`ChevronDown`/`ChevronUp`) + existing edit/duplicate/delete dropdown targeting outgoing transaction

Clicking the **row body** opens the edit modal (same as regular rows).  
Clicking the **expand chevron** button (in or near the actions column) toggles expansion without opening edit.

### Expanded Sub-rows (desktop)

Two `<TableRow>` elements inserted immediately after the collapsed row:

- `bg-muted/30` background, no hover
- Cells: empty checkbox cell, empty date cell, account name (md+), empty category (lg+), empty payee, empty description, amount colored by sign, empty files, empty actions

---

## Section 3 — Infinite Scroll (All Viewports)

### Backend

In `TransactionController`, wrap the existing `Inertia::defer()` call with `Inertia::scroll()`:

```php
'transactions' => Inertia::defer(fn () => Inertia::scroll(
    $this->buildTransactionQuery($ledger, $filters)->paginate(25)
)),
```

`Inertia::scroll()` automatically configures the proper append-merge behavior and normalises pagination metadata for the `InfiniteScroll` frontend component. Wrapping it in `Inertia::defer()` keeps the initial page load fast (deferred network request).

### Frontend — Loading Next Page

Replace the pagination controls (`handlePageChange` function + prev/next UI) with the `InfiniteScroll` component from `@inertiajs/react`:

```tsx
import { InfiniteScroll } from '@inertiajs/react';

<InfiniteScroll data="transactions">{/* list content here */}</InfiniteScroll>;
```

`InfiniteScroll` wraps the entire list content. It:

- Automatically detects when the user scrolls near the bottom
- Fires the next-page request (merging into `transactions.data`)
- Shows a `fallback` while loading

### Filter Changes — Resetting Merged Data

When filters change, the accumulated merged `transactions` array must be cleared before loading page 1 with the new filter. Add `reset: ['transactions']` to all filter/reset requests:

```ts
router.get(url, params, {
    only: ['transactions', 'filters'],
    reset: ['transactions'], // clears the merged array
    preserveState: true,
    replace: true,
});
```

This applies to `applyFiltersWith()` and `handleResetFilters()`.

### Running Balance Adjustment

The current running balance guard:

```ts
if (txns.length === 0 || transactions.current_page !== 1) {
    return null;
}
```

With infinite scroll, `current_page` will be the **last loaded page** (e.g. 3 after scrolling through 3 pages), but `transactions.data` always starts from page 1 because filter changes reset the array. Remove the `current_page !== 1` check — safe because `reset: ['transactions']` on filter changes guarantees the array always begins at page 1. The balance calculation correctly walks the full accumulated array forward. The only remaining guard needed is `txns.length === 0` and the single-account filter check.

---

## Components / Files Affected

| File                             | Change                                                                                     |
| -------------------------------- | ------------------------------------------------------------------------------------------ |
| `mobile-transaction-list.tsx`    | Replace `transfer_pair` block with collapsed row + expand toggle + sub-rows                |
| `mobile-transaction-row-data.ts` | Update `resolveMobileTransactionTitle` to return `"From → To"` format for paired transfers |
| `index.tsx` — desktop table      | Use `groupTransactionsForMobile`, render collapsed/expandable transfer rows                |
| `index.tsx` — pagination         | Remove pagination controls; add `WhenVisible` at bottom of list                            |
| `index.tsx` — running balance    | Remove `current_page !== 1` guard                                                          |
| `TransactionController.php`      | Add `.merge()` to deferred transactions prop                                               |
| `mobile-transaction-groups.ts`   | No change (already handles grouping correctly)                                             |

---

## Testing

### Backend

- Update `TransactionPageTest.php` to assert the deferred prop is returned correctly (merge behaviour is transparent to tests — the paginator shape is unchanged)
- Add a test asserting that `reset: ['transactions']` on a filter change correctly returns page 1 results (verify the paginator's `current_page` is 1 and `data` is fresh, not accumulated)

### Frontend (Node test files)

- Update `mobile-transaction-row-data.test.ts` to cover the updated `resolveMobileTransactionTitle` format (`"From → To"`) for paired and standalone (unpaired) transfer cases
- No changes needed to `mobile-transaction-groups.test.ts` (grouping logic unchanged)
