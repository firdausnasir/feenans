# Transactions UI Phase 2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign transfer pair rows to be collapsed/expandable on both mobile and desktop, and replace pagination with infinite scroll on all viewports.

**Architecture:** Four sequential tasks with Tasks A and B parallelisable. A shared `resolveTransferPairTitle` helper drives both mobile and desktop collapsed rows. Infinite scroll uses a custom `IntersectionObserver` sentinel (no `Inertia::scroll()` required) combined with `->deepMerge()` on the deferred prop. Filter resets use the Inertia `reset` request option to clear the merged array before loading page 1.

**Tech Stack:** Laravel 12, Inertia v2, React 19, Tailwind v4, Pest 4, Node built-in test runner

**Spec:** `docs/superpowers/specs/2026-03-29-transactions-mobile-ui-phase2-design.md`

---

## File Map

| File                                                                          | Change                                                                        |
| ----------------------------------------------------------------------------- | ----------------------------------------------------------------------------- |
| `app/Http/Controllers/Ledger/TransactionController.php`                       | Add `->deepMerge()` on the deferred prop                                      |
| `resources/js/pages/ledgers/transactions/mobile-transaction-row-data.ts`      | Add `resolveTransferPairTitle()`                                              |
| `resources/js/pages/ledgers/transactions/mobile-transaction-row-data.test.ts` | Add tests for new function                                                    |
| `resources/js/pages/ledgers/transactions/mobile-transaction-list.tsx`         | Collapsed + expandable transfer pair row                                      |
| `resources/js/pages/ledgers/transactions/index.tsx`                           | Desktop collapsed rows + infinite scroll + filter reset + running balance fix |
| `tests/Feature/Ledger/TransactionPageTest.php`                                | No changes needed (paginator shape unchanged by deepMerge)                    |

---

## Task A: Backend — Enable Merge on Deferred Transactions Prop

**Files:**

- Modify: `app/Http/Controllers/Ledger/TransactionController.php:53`

**Context:** The existing deferred prop returns a `LengthAwarePaginator`. Chaining `->deepMerge()` on the `Inertia::defer()` call tells Inertia to append `data` arrays (and update scalar fields like `current_page`) when the frontend requests additional pages instead of replacing the whole prop.

- [ ] **Step 1: Add `->deepMerge()` to the deferred transactions prop**

In `app/Http/Controllers/Ledger/TransactionController.php`, change line 53:

```php
// Before:
'transactions' => Inertia::defer(function () use ($ledger, $request, $filters, $page) {
    ...
    return $transactions;
}),

// After:
'transactions' => Inertia::defer(function () use ($ledger, $request, $filters, $page) {
    ...
    return $transactions;
})->deepMerge(),
```

Only the trailing `)` changes to `)->deepMerge()`. The callback body is unchanged.

- [ ] **Step 2: Run Pint and existing tests to verify nothing broke**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact --filter=TransactionPageTest
```

Expected: all existing `TransactionPageTest` tests pass — the `deepMerge` is transparent to Inertia's test helpers since the paginator shape does not change.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Ledger/TransactionController.php
git commit -m "feat: enable deepMerge on deferred transactions prop for infinite scroll"
```

---

## Task B: TS Helper — Transfer Pair Title

**Files:**

- Modify: `resources/js/pages/ledgers/transactions/mobile-transaction-row-data.ts`
- Modify: `resources/js/pages/ledgers/transactions/mobile-transaction-row-data.test.ts`

**Context:** A collapsed transfer pair row needs a single title like `"Checking → Savings"`. The existing `resolveMobileTransactionTitle` works for single-leg standalone transfers. A new `resolveTransferPairTitle` function handles the case where both legs are available.

- [ ] **Step 1: Write the failing tests first**

Add to the end of `resources/js/pages/ledgers/transactions/mobile-transaction-row-data.test.ts`:

```ts
const { resolveTransferPairTitle } = await import(
    new URL('./mobile-transaction-row-data.ts', import.meta.url).href
);

// --- resolveTransferPairTitle ---

function makeAccount(name: string) {
    return {
        id: 1,
        ledger_id: 1,
        account_type_id: 1,
        name,
        color: null,
        initial_balance: '0.00',
        statement_day: null,
        payment_due_day: null,
        include_in_totals: true,
        is_hidden: false,
        position: 1,
        current_balance: '0.00',
    };
}

test('resolveTransferPairTitle returns "From → To" using outgoing and incoming account names', () => {
    const outgoing = makeTransaction({
        id: 1,
        transaction_type: 'transfer',
        amount: '-50.00',
        account: makeAccount('Checking'),
    });
    const incoming = makeTransaction({
        id: 2,
        transaction_type: 'transfer',
        amount: '50.00',
        account: makeAccount('Savings'),
    });

    assert.equal(
        resolveTransferPairTitle([outgoing, incoming]),
        'Checking → Savings',
    );
});

test('resolveTransferPairTitle handles reversed order (incoming first)', () => {
    const outgoing = makeTransaction({
        id: 1,
        transaction_type: 'transfer',
        amount: '-50.00',
        account: makeAccount('Checking'),
    });
    const incoming = makeTransaction({
        id: 2,
        transaction_type: 'transfer',
        amount: '50.00',
        account: makeAccount('Savings'),
    });

    assert.equal(
        resolveTransferPairTitle([incoming, outgoing]),
        'Checking → Savings',
    );
});

test('resolveTransferPairTitle falls back to "Transfer" when account names are missing', () => {
    const outgoing = makeTransaction({
        id: 1,
        transaction_type: 'transfer',
        amount: '-50.00',
    });
    const incoming = makeTransaction({
        id: 2,
        transaction_type: 'transfer',
        amount: '50.00',
    });

    assert.equal(resolveTransferPairTitle([outgoing, incoming]), 'Transfer');
});
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
node --experimental-strip-types --test resources/js/pages/ledgers/transactions/mobile-transaction-row-data.test.ts
```

Expected: 3 new tests fail with `resolveTransferPairTitle is not a function`.

- [ ] **Step 3: Implement `resolveTransferPairTitle`**

Add to `resources/js/pages/ledgers/transactions/mobile-transaction-row-data.ts`:

```ts
export function resolveTransferPairTitle(transactions: Transaction[]): string {
    const outgoing = transactions.find((t) => parseFloat(t.amount ?? '0') < 0);
    const incoming = transactions.find((t) => parseFloat(t.amount ?? '0') > 0);

    const fromName = outgoing?.account?.name;
    const toName = incoming?.account?.name;

    if (fromName && toName) {
        return `${fromName} → ${toName}`;
    }

    return 'Transfer';
}
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
node --experimental-strip-types --test resources/js/pages/ledgers/transactions/mobile-transaction-row-data.test.ts
```

Expected: all tests pass.

- [ ] **Step 5: Lint**

```bash
npm run lint
```

Fix any errors.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/ledgers/transactions/mobile-transaction-row-data.ts \
        resources/js/pages/ledgers/transactions/mobile-transaction-row-data.test.ts
git commit -m "feat: add resolveTransferPairTitle helper for collapsed transfer pair rows"
```

---

## Task C: Mobile — Collapsed + Expandable Transfer Pair Row

**Files:**

- Modify: `resources/js/pages/ledgers/transactions/mobile-transaction-list.tsx`

**Context:** Currently a `transfer_pair` group renders as a `bg-muted/20` block with a floating "TRANSFER" label above two separate rows. The new design replaces this with a single collapsed row (identical in structure to a regular row) plus an expand toggle — identical to the existing split expander pattern already in this file.

**Requires Task B to be complete.**

- [ ] **Step 1: Import `resolveTransferPairTitle` and `ChevronDown`/`ChevronUp`**

At the top of `mobile-transaction-list.tsx`, the `ChevronDown` and `ChevronUp` imports already exist. Add `resolveTransferPairTitle` to the import from `./mobile-transaction-row-data`:

```ts
import {
    resolveMobileTransactionTitle,
    resolveTransferPairTitle,
} from './mobile-transaction-row-data';
```

- [ ] **Step 2: Add expand state for transfer pairs**

Add alongside the existing `expandedSplitIds` state:

```ts
const [expandedTransferPairIds, setExpandedTransferPairIds] = useState<
    string[]
>([]);

function toggleTransferPair(pairId: string): void {
    setExpandedTransferPairIds((current) =>
        current.includes(pairId)
            ? current.filter((id) => id !== pairId)
            : [...current, pairId],
    );
}
```

- [ ] **Step 3: Replace the `transfer_pair` group rendering**

Find the block in `MobileTransactionList` that renders `item.kind === 'transfer_pair'` (lines 326–342 in the current file). Replace it entirely with:

```tsx
if (item.kind === 'transfer_pair') {
    const outgoing =
        item.transactions.find((t) => parseFloat(t.amount ?? '0') < 0) ??
        item.transactions[0];
    const selected = isSelected(outgoing);
    const amount = parseFloat(outgoing.amount ?? '0');
    const pairTitle = resolveTransferPairTitle(item.transactions);
    const isPairExpanded = expandedTransferPairIds.includes(item.pairId);
    const hasAttachments =
        (outgoing.attachments?.length ?? 0) > 0 ||
        (outgoing.attachments_count ?? 0) > 0;

    return (
        <div
            key={item.pairId}
            className={cn(
                'px-3 py-2.5 transition-colors',
                bordered,
                selected && 'bg-primary/5',
            )}
        >
            <div className="grid grid-cols-[auto_1fr_auto] gap-2">
                {/* Checkbox — targets outgoing */}
                <div
                    className="flex size-10 items-start justify-center pt-1"
                    onClick={() => onSelectOne(outgoing.id, !selected)}
                >
                    <Checkbox
                        checked={selected}
                        onCheckedChange={(checked) =>
                            onSelectOne(outgoing.id, checked)
                        }
                        onClick={(e) => e.stopPropagation()}
                        aria-label={`Select transfer ${item.pairId}`}
                    />
                </div>

                {/* Content */}
                <div className="min-w-0">
                    <button
                        type="button"
                        className="flex w-full items-start justify-between gap-3 text-left"
                        onClick={() => onEdit(outgoing)}
                    >
                        <div className="min-w-0 space-y-0.5">
                            <div className="flex min-w-0 items-center gap-1.5">
                                <ArrowRightLeft className="size-3.5 shrink-0 text-muted-foreground" />
                                <span className="truncate text-sm font-medium">
                                    {pairTitle}
                                </span>
                            </div>

                            {outgoing.description && (
                                <p className="truncate text-xs text-muted-foreground">
                                    {outgoing.description}
                                </p>
                            )}
                        </div>

                        <div className="shrink-0 text-right">
                            <p
                                className={cn(
                                    'text-sm font-semibold tabular-nums',
                                    'text-foreground',
                                )}
                            >
                                {formatAbsAmount(outgoing.amount, privacyMode)}
                            </p>
                        </div>
                    </button>

                    {/* Expand toggle */}
                    <div className="mt-1.5">
                        <button
                            type="button"
                            className="inline-flex min-h-8 items-center gap-1 text-[11px] text-muted-foreground hover:text-foreground"
                            onClick={() => toggleTransferPair(item.pairId)}
                        >
                            {isPairExpanded ? (
                                <ChevronUp className="size-3" />
                            ) : (
                                <ChevronDown className="size-3" />
                            )}
                            2 accounts
                        </button>

                        {isPairExpanded && (
                            <div className="mt-1.5 space-y-1 rounded-lg bg-muted/40 px-2.5 py-2">
                                {item.transactions.map((tx) => (
                                    <div
                                        key={tx.id}
                                        className="flex items-start justify-between gap-2"
                                    >
                                        <p className="truncate text-[11px] font-medium text-foreground">
                                            {tx.account?.name ??
                                                'Unknown account'}
                                        </p>
                                        <span
                                            className={cn(
                                                'shrink-0 text-[11px] tabular-nums',
                                                amountColor(
                                                    parseFloat(
                                                        tx.amount ?? '0',
                                                    ),
                                                ),
                                            )}
                                        >
                                            {formatAbsAmount(
                                                tx.amount,
                                                privacyMode,
                                            )}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* Actions — targets outgoing */}
                <div className="flex min-h-10 items-start gap-0.5">
                    {hasAttachments && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-8 text-muted-foreground"
                            onClick={() => onAttachmentClick(outgoing)}
                        >
                            <Paperclip className="size-3.5" />
                        </Button>
                    )}

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-8 text-muted-foreground"
                            >
                                <MoreVertical className="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem onClick={() => onEdit(outgoing)}>
                                Edit
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onClick={() => onDuplicate(outgoing)}
                            >
                                Duplicate
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                className="text-destructive focus:text-destructive"
                                onClick={() => onDelete(outgoing)}
                            >
                                Delete
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </div>
    );
}
```

Also update the surrounding wrapper: the `transfer_pair` block no longer needs a separate outer `<div className={cn('bg-muted/20', bordered)}>` wrapper — the row itself handles selection highlight. The `bordered` class must move onto the item's own container (as shown in the code above with `bordered` applied to the row `div`).

Update the rendering loop to pass `bordered` correctly to the transfer pair case. The `bordered` variable is currently computed as:

```ts
const bordered = index > 0 ? 'border-t border-border/70' : '';
```

This should be passed into the transfer pair rendering as shown above. For regular `transaction` items, the existing `<div key={item.transaction.id} className={bordered}>` wrapper remains unchanged.

- [ ] **Step 4: Lint**

```bash
npm run lint
```

Fix any errors.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/ledgers/transactions/mobile-transaction-list.tsx
git commit -m "feat: collapse transfer pairs into single expandable row on mobile"
```

---

## Task D: Desktop Table + Infinite Scroll

**Files:**

- Modify: `resources/js/pages/ledgers/transactions/index.tsx`

**Context:** This is the largest task. It touches `index.tsx` to:

1. Apply `groupTransactionsForMobile` to the desktop table — rendering collapsed/expandable transfer rows
2. Add infinite scroll via `IntersectionObserver` sentinel at the bottom of the list
3. Add `reset: ['transactions']` to all filter/reset router calls
4. Remove the `current_page !== 1` guard on running balances
5. Remove the pagination controls UI

**Requires Tasks A, B, and C to be complete.**

### D1 — Desktop collapsed transfer rows

- [ ] **Step 1: Add imports**

At the top of `index.tsx`, add:

```ts
import { groupTransactionsForMobile } from './mobile-transaction-groups';
import type { MobileTransactionListItem } from './mobile-transaction-groups';
import { resolveTransferPairTitle } from './mobile-transaction-row-data';
```

- [ ] **Step 2: Add expand state for desktop transfer pairs**

Inside `TransactionsIndex`, add:

```ts
const [expandedDesktopPairIds, setExpandedDesktopPairIds] = useState<string[]>(
    [],
);

function toggleDesktopTransferPair(pairId: string): void {
    setExpandedDesktopPairIds((current) =>
        current.includes(pairId)
            ? current.filter((id) => id !== pairId)
            : [...current, pairId],
    );
}
```

- [ ] **Step 3: Replace the desktop `<TableBody>` loop**

The current `renderTransactionList` has a flat `txs.data.map((tx) => ...)` loop inside `<TableBody>`. Replace this with a grouped rendering.

First, group the data:

```tsx
const groups = groupTransactionsForMobile(txs.data);
const flatItems: { item: MobileTransactionListItem; bordered: boolean }[] = [];
for (const group of groups) {
    group.items.forEach((item, idx) => {
        flatItems.push({ item, bordered: idx > 0 });
    });
}
```

Then map `flatItems` inside `<TableBody>`:

```tsx
{
    flatItems.flatMap(({ item }) => {
        if (item.kind === 'transfer_pair') {
            const outgoing =
                item.transactions.find(
                    (t) => parseFloat(t.amount ?? '0') < 0,
                ) ?? item.transactions[0];
            const isPairExpanded = expandedDesktopPairIds.includes(item.pairId);
            const pairTitle = resolveTransferPairTitle(item.transactions);
            const pairAmount = parseFloat(outgoing.amount ?? '0');
            const isOutgoingSelected = allAcrossPages
                ? !excludedIds.includes(outgoing.id)
                : selectedIds.includes(outgoing.id);

            const rows = [
                // Collapsed row
                <TableRow
                    key={`pair-${item.pairId}`}
                    className="cursor-pointer"
                    onClick={() => setEditTransaction(outgoing)}
                >
                    <TableCell onClick={(e) => e.stopPropagation()}>
                        <Checkbox
                            checked={isOutgoingSelected}
                            onCheckedChange={(c) =>
                                handleSelectOne(outgoing.id, c)
                            }
                        />
                    </TableCell>
                    <TableCell className="whitespace-nowrap">
                        {formatDate(outgoing.transaction_date)}
                    </TableCell>
                    <TableCell className="hidden md:table-cell">
                        <span className="inline-flex items-center gap-1.5">
                            <ArrowRightLeft className="size-3.5 shrink-0 text-muted-foreground" />
                            {pairTitle}
                        </span>
                    </TableCell>
                    <TableCell className="hidden lg:table-cell" />
                    <TableCell />
                    <TableCell>{outgoing.description}</TableCell>
                    <TableCell className="text-right font-medium text-foreground tabular-nums">
                        {formatAbsAmount(pairAmount, privacyMode)}
                    </TableCell>
                    <TableCell
                        className="text-center"
                        onClick={(e) => e.stopPropagation()}
                    >
                        {(outgoing.attachments_count ?? 0) > 0 && (
                            <button
                                type="button"
                                onClick={() =>
                                    setAttachmentModalTransaction(outgoing)
                                }
                                className="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted/80 hover:text-foreground"
                            >
                                <Paperclip className="size-3" />
                                {outgoing.attachments_count}
                            </button>
                        )}
                    </TableCell>
                    <TableCell onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center gap-0.5">
                            <button
                                type="button"
                                className="flex size-7 items-center justify-center rounded text-muted-foreground hover:bg-muted"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    toggleDesktopTransferPair(item.pairId);
                                }}
                            >
                                {isPairExpanded ? (
                                    <ChevronUp className="size-4" />
                                ) : (
                                    <ChevronDown className="size-4" />
                                )}
                            </button>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <button
                                        type="button"
                                        className="flex size-7 items-center justify-center rounded text-muted-foreground hover:bg-muted"
                                    >
                                        <MoreVertical className="size-4" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem
                                        onClick={() =>
                                            setEditTransaction(outgoing)
                                        }
                                    >
                                        Edit
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onClick={() =>
                                            handleDuplicate(outgoing)
                                        }
                                    >
                                        Duplicate
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        className="text-destructive focus:text-destructive"
                                        onClick={() =>
                                            setDeleteConfirmTransaction(
                                                outgoing,
                                            )
                                        }
                                    >
                                        Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </TableCell>
                </TableRow>,
            ];

            // Sub-rows when expanded
            if (isPairExpanded) {
                item.transactions.forEach((tx) => {
                    const txAmount = parseFloat(tx.amount ?? '0');
                    rows.push(
                        <TableRow
                            key={`pair-leg-${tx.id}`}
                            className="bg-muted/30 hover:bg-muted/30"
                        >
                            <TableCell />
                            <TableCell />
                            <TableCell className="hidden pl-8 text-sm text-muted-foreground md:table-cell">
                                {tx.account?.name}
                            </TableCell>
                            <TableCell className="hidden lg:table-cell" />
                            <TableCell />
                            <TableCell />
                            <TableCell
                                className={cn(
                                    'text-right text-sm tabular-nums',
                                    amountColor(txAmount),
                                )}
                            >
                                {formatAbsAmount(txAmount, privacyMode)}
                            </TableCell>
                            <TableCell />
                            <TableCell />
                        </TableRow>,
                    );
                });
            }

            return rows;
        }

        // Regular transaction row (same as existing code)
        const tx = item.transaction;
        const amount = parseFloat(tx.amount);
        return [
            <TableRow
                key={tx.id}
                className="cursor-pointer"
                onClick={() => setEditTransaction(tx)}
            >
                <TableCell onClick={(e) => e.stopPropagation()}>
                    <Checkbox
                        checked={
                            allAcrossPages
                                ? !excludedIds.includes(tx.id)
                                : selectedIds.includes(tx.id)
                        }
                        onCheckedChange={(c) => handleSelectOne(tx.id, c)}
                    />
                </TableCell>
                <TableCell className="whitespace-nowrap">
                    {formatDate(tx.transaction_date)}
                </TableCell>
                <TableCell className="hidden md:table-cell">
                    {tx.account?.name}
                </TableCell>
                <TableCell className="hidden lg:table-cell">
                    {tx.category?.name}
                </TableCell>
                <TableCell>{tx.payee?.name}</TableCell>
                <TableCell>{tx.description}</TableCell>
                <TableCell
                    className={`text-right font-medium tabular-nums ${amountColor(amount)}`}
                >
                    {formatAbsAmount(amount, privacyMode)}
                </TableCell>
                <TableCell
                    className="text-center"
                    onClick={(e) => e.stopPropagation()}
                >
                    {(tx.attachments_count ?? 0) > 0 && (
                        <button
                            type="button"
                            onClick={() => setAttachmentModalTransaction(tx)}
                            className="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted/80 hover:text-foreground"
                        >
                            <Paperclip className="size-3" />
                            {tx.attachments_count}
                        </button>
                    )}
                </TableCell>
                <TableCell onClick={(e) => e.stopPropagation()}>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button
                                type="button"
                                className="flex size-7 items-center justify-center rounded text-muted-foreground hover:bg-muted"
                            >
                                <MoreVertical className="size-4" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                onClick={() => setEditTransaction(tx)}
                            >
                                Edit
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onClick={() => handleDuplicate(tx)}
                            >
                                Duplicate
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                className="text-destructive focus:text-destructive"
                                onClick={() => setDeleteConfirmTransaction(tx)}
                            >
                                Delete
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </TableCell>
            </TableRow>,
        ];
    });
}
```

The `ChevronUp` and `ChevronDown` icons must be imported at the top of `index.tsx` (add to the existing lucide import block if not already present).

### D2 — Infinite scroll

- [ ] **Step 4: Add IntersectionObserver state and ref**

Add inside `TransactionsIndex`:

```ts
const infiniteScrollSentinelRef = useRef<HTMLDivElement>(null);
const infiniteScrollLoadingRef = useRef(false);
```

- [ ] **Step 5: Add the IntersectionObserver effect**

Add after the existing `useEffect` for `isMobile`:

```ts
useEffect(() => {
    const sentinel = infiniteScrollSentinelRef.current;
    if (!sentinel) return;

    const observer = new IntersectionObserver(
        (entries) => {
            const entry = entries[0];
            if (
                entry.isIntersecting &&
                !infiniteScrollLoadingRef.current &&
                transactions?.next_page_url
            ) {
                infiniteScrollLoadingRef.current = true;

                const nextUrl = new URL(transactions.next_page_url);
                const params: Record<string, string | string[]> =
                    buildQueryParams(committedFilters);
                params.page = nextUrl.searchParams.get('page') ?? '2';

                router.get(transactionsIndex.url(ledger.id), params, {
                    only: ['transactions'],
                    preserveState: true,
                    replace: true,
                    onFinish: () => {
                        infiniteScrollLoadingRef.current = false;
                    },
                });
            }
        },
        { rootMargin: '200px' },
    );

    observer.observe(sentinel);
    return () => observer.disconnect();
}, [transactions?.next_page_url, committedFilters, ledger.id]);
```

- [ ] **Step 6: Add `reset: ['transactions']` to filter requests**

In `applyFiltersWith()`:

```ts
router.get(transactionsIndex.url(ledger.id), params, {
    only: ['transactions', 'filters'],
    reset: ['transactions'], // add this line
    preserveState: true,
    preserveScroll: true,
    replace: true,
});
```

In `handleResetFilters()`:

```ts
router.get(
    transactionsIndex.url(ledger.id),
    {},
    {
        only: ['transactions', 'filters'],
        reset: ['transactions'], // add this line
        preserveState: true,
        preserveScroll: true,
        replace: true,
    },
);
```

- [ ] **Step 7: Fix running balance — remove `current_page !== 1` guard**

Find the `runningBalances` useMemo. Change:

```ts
if (txns.length === 0 || transactions.current_page !== 1) {
    return null;
}
```

to:

```ts
if (txns.length === 0) {
    return null;
}
```

- [ ] **Step 8: Remove `handlePageChange` function and pagination UI**

Delete the entire `handlePageChange` function (about 12 lines).

In `renderTransactionList`, find and delete the pagination block. It looks roughly like:

```tsx
{
    /* Pagination controls */
}
{
    txs.last_page > 1 && (
        <div className="flex items-center justify-between ...">...</div>
    );
}
```

Delete the full pagination block (the exact code block containing the page navigation buttons and "Showing X of Y" type pagination info that's separate from the result count at the top).

Note: keep the "Showing X-Y of Z" text at the top of the list — that's informational, not pagination controls.

- [ ] **Step 9: Add sentinel + loading skeleton to `renderTransactionList`**

At the very end of `renderTransactionList`, after the `MobileTransactionList` component and before the closing `<>`, add:

```tsx
{
    /* Infinite scroll sentinel */
}
{
    txs.next_page_url && (
        <>
            <div ref={infiniteScrollSentinelRef} className="h-1" />
            {infiniteScrollLoadingRef.current && (
                <div className="py-4 text-center text-sm text-muted-foreground">
                    Loading more...
                </div>
            )}
        </>
    );
}
```

Actually, `infiniteScrollLoadingRef.current` won't trigger a re-render. Replace the loading indicator approach with a simple skeleton that always shows when `next_page_url` is present and loading is in-flight. Use a small state variable instead:

```ts
const [isLoadingMore, setIsLoadingMore] = useState(false);
```

Update the `onFinish` in the IntersectionObserver effect:

```ts
onStart: () => setIsLoadingMore(true),
onFinish: () => {
    infiniteScrollLoadingRef.current = false;
    setIsLoadingMore(false);
},
```

And in the sentinel block:

```tsx
{
    txs.next_page_url && (
        <>
            <div ref={infiniteScrollSentinelRef} className="h-1" />
            {isLoadingMore && (
                <div className="space-y-2 py-3 sm:hidden">
                    {Array.from({ length: 2 }).map((_, i) => (
                        <div
                            key={i}
                            className="rounded-xl border border-border px-3 py-3"
                        >
                            <div className="grid grid-cols-[auto_1fr_auto] gap-2">
                                <Skeleton className="mt-1 size-4 rounded" />
                                <div className="space-y-1.5">
                                    <Skeleton className="h-4 w-32" />
                                    <Skeleton className="h-3 w-48" />
                                </div>
                                <Skeleton className="h-4 w-16" />
                            </div>
                        </div>
                    ))}
                </div>
            )}
            {isLoadingMore && (
                <div className="hidden space-y-1 py-2 sm:block">
                    {Array.from({ length: 3 }).map((_, i) => (
                        <div
                            key={i}
                            className="flex items-center gap-3 border-b border-border px-2 py-3"
                        >
                            <Skeleton className="size-4 rounded" />
                            <Skeleton className="h-4 w-20" />
                            <Skeleton className="h-4 w-48 flex-1" />
                            <Skeleton className="h-4 w-20" />
                            <Skeleton className="size-4" />
                        </div>
                    ))}
                </div>
            )}
        </>
    );
}
```

Also add `isLoadingMore` state declaration and update the effect to use `setIsLoadingMore`.

Note: `router.get()` doesn't have an `onStart` callback. Use `setIsLoadingMore(true)` before calling `router.get()` instead:

```ts
infiniteScrollLoadingRef.current = true;
setIsLoadingMore(true);

router.get(transactionsIndex.url(ledger.id), params, {
    only: ['transactions'],
    preserveState: true,
    replace: true,
    onFinish: () => {
        infiniteScrollLoadingRef.current = false;
        setIsLoadingMore(false);
    },
});
```

- [ ] **Step 10: Lint + types check**

```bash
npm run lint
npm run types:check
```

Fix all errors. Common issues to watch for:

- Missing `ChevronUp`/`ChevronDown` imports in `index.tsx`
- `flatMap` return type needing explicit `ReactNode[]` annotation
- `item.kind === 'transaction'` check (the type is `'transaction'`, not `'single'`)

- [ ] **Step 11: Commit**

```bash
git add resources/js/pages/ledgers/transactions/index.tsx
git commit -m "feat: desktop transfer pair grouping, infinite scroll, filter reset, running balance fix"
```

---

## Final Verification

- [ ] Run full test suite:

```bash
php artisan test --compact --filter=Transaction
```

Expected: all transaction-related tests pass.

- [ ] Run lint + types:

```bash
npm run lint && npm run types:check
```

Expected: zero errors.
