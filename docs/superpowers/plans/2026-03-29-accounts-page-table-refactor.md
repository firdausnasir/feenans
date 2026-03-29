# Accounts Page Table Refactor — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the card-based accounts page with a dense table view (desktop) / compact cards (mobile), regroup by include_in_totals, and remove credit card statement balance computation.

**Architecture:** Backend changes first (remove statement computation, add new grouping method, trim resource), then frontend (new types, table/card layout, collapsible groups, drag-and-drop). TDD for all backend changes. Existing `groupAccountsByType()` is preserved for the dashboard.

**Tech Stack:** Laravel 12, Inertia v2, React 19, Tailwind CSS v4, Pest 4

---

## File Map

| Action | File                                                | Responsibility                                                                   |
| ------ | --------------------------------------------------- | -------------------------------------------------------------------------------- |
| Modify | `app/Http/Controllers/Ledger/AccountController.php` | Remove statement computation, add `groupAccountsByTotals()`, wire into `index()` |
| Modify | `app/Http/Resources/AccountResource.php`            | Remove conditional statement fields                                              |
| Modify | `tests/Feature/Ledger/AccountTest.php`              | Replace statement balance tests with new grouping tests                          |
| Modify | `tests/Feature/AccountReorderTest.php`              | Update assertions for new `accounts` prop shape                                  |
| Modify | `tests/Feature/HideAccountTest.php`                 | Update assertions if needed for new prop shape                                   |
| Modify | `resources/js/pages/ledgers/accounts/index.tsx`     | Table layout, mobile cards, collapsible groups, updated types                    |
| Modify | `resources/js/types/ledger.ts`                      | No changes needed (Account type already has all needed fields)                   |

---

### Task 1: Backend — Remove statement balance computation and trim AccountResource

**Files:**

- Modify: `app/Http/Controllers/Ledger/AccountController.php:40-98`
- Modify: `app/Http/Resources/AccountResource.php:36-41`
- Modify: `tests/Feature/Ledger/AccountTest.php:110-175`

- [ ] **Step 1: Replace the two statement balance tests with tests asserting those fields are absent**

In `tests/Feature/Ledger/AccountTest.php`, replace the two tests at lines 110-175 with:

```php
test('credit card accounts do not include computed statement fields', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->credit()->create();
    Account::factory()->for($ledger)->for($accountType)->create([
        'initial_balance' => 0,
        'statement_day' => 15,
        'payment_due_day' => 25,
    ]);

    $this->actingAs($user)
        ->get(route('ledgers.accounts.index', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('accounts', 1)
            ->has('accounts.0.accounts', 1)
            ->where('accounts.0.accounts.0.statement_day', 15)
            ->where('accounts.0.accounts.0.payment_due_day', 25)
            ->missing('accounts.0.accounts.0.statement_balance')
            ->missing('accounts.0.accounts.0.current_spending')
            ->missing('accounts.0.accounts.0.outstanding')
            ->missing('accounts.0.accounts.0.payment_due_date')
            ->missing('accounts.0.accounts.0.statement_start')
            ->missing('accounts.0.accounts.0.statement_end')
        );
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter='credit card accounts do not include computed statement fields'`
Expected: FAIL — the response currently includes `statement_balance` etc.

- [ ] **Step 3: Remove statement computation from controller**

In `app/Http/Controllers/Ledger/AccountController.php`, replace the `buildGroupedAccounts` method (lines 40-98) with:

```php
/**
 * @return array<int, array{type: array, accounts: array, total_balance: string}>
 */
private function buildGroupedAccounts(Ledger $ledger, $accountTypes): array
{
    $accounts = $ledger->accounts()
        ->with('accountType')
        ->visible()
        ->orderBy('position')
        ->orderBy('name')
        ->get();

    return self::groupAccountsByType($accounts, $accountTypes);
}
```

Note: the `TransactionService $txService` parameter is no longer needed by `buildGroupedAccounts`. Also update the `index` method signature and call:

In the `index` method (line 24), remove the `TransactionService $txService` parameter and update the call at line 31:

```php
public function index(Ledger $ledger, Request $request): Response
{
    $this->authorize('view', $ledger);

    $accountTypes = $ledger->accountTypes()->orderBy('position')->get();

    return Inertia::render('ledgers/accounts/index', [
        'accounts' => fn () => $this->buildGroupedAccounts($ledger, $accountTypes),
        'accountTypes' => fn () => $accountTypes,
        'netWorth' => Inertia::defer(fn () => $this->buildNetWorth($ledger)),
    ]);
}
```

Remove the `use App\Services\TransactionService;` and `use Carbon\CarbonImmutable;` imports if no longer used elsewhere in the file. Check: `CarbonImmutable` is still used in `adjustBalance` (line 253), so keep it. `TransactionService` is only used in `buildGroupedAccounts`, so remove it.

- [ ] **Step 4: Remove conditional statement fields from AccountResource**

In `app/Http/Resources/AccountResource.php`, remove lines 36-41 (the six `$this->when(...)` lines for `statement_start`, `statement_end`, `statement_balance`, `current_spending`, `outstanding`, `payment_due_date`).

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter='credit card accounts do not include computed statement fields'`
Expected: PASS

- [ ] **Step 6: Run full account test suite to check for regressions**

Run: `php artisan test --compact tests/Feature/Ledger/AccountTest.php tests/Feature/Ledger/AccountCrudTest.php tests/Feature/HideAccountTest.php`
Expected: All pass

- [ ] **Step 7: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Ledger/AccountController.php app/Http/Resources/AccountResource.php tests/Feature/Ledger/AccountTest.php
git commit -m "Remove credit card statement balance computation and trim AccountResource"
```

---

### Task 2: Backend — Regroup accounts by include_in_totals

**Files:**

- Modify: `app/Http/Controllers/Ledger/AccountController.php`
- Modify: `tests/Feature/Ledger/AccountTest.php`
- Modify: `tests/Feature/AccountReorderTest.php`

- [ ] **Step 1: Write tests for new grouping**

Add to `tests/Feature/Ledger/AccountTest.php`:

```php
test('accounts index groups by include_in_totals', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $type = AccountType::factory()->for($ledger)->create();

    Account::factory()->for($ledger)->for($type)->create([
        'name' => 'Checking',
        'include_in_totals' => true,
        'initial_balance' => 1000,
    ]);
    Account::factory()->for($ledger)->for($type)->create([
        'name' => 'Rainy Day',
        'include_in_totals' => false,
        'initial_balance' => 5000,
    ]);

    $this->actingAs($user)
        ->get(route('ledgers.accounts.index', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('accounts', 2)
            ->where('accounts.0.group', 'included')
            ->where('accounts.0.label', 'Included in totals')
            ->has('accounts.0.accounts', 1)
            ->where('accounts.0.accounts.0.name', 'Checking')
            ->where('accounts.0.total_balance', '1000.00')
            ->where('accounts.1.group', 'excluded')
            ->where('accounts.1.label', 'Savings')
            ->has('accounts.1.accounts', 1)
            ->where('accounts.1.accounts.0.name', 'Rainy Day')
            ->where('accounts.1.total_balance', '5000.00')
        );
});

test('accounts index omits empty groups', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $type = AccountType::factory()->for($ledger)->create();

    Account::factory()->for($ledger)->for($type)->create([
        'name' => 'Checking',
        'include_in_totals' => true,
    ]);

    $this->actingAs($user)
        ->get(route('ledgers.accounts.index', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('accounts', 1)
            ->where('accounts.0.group', 'included')
        );
});

test('accounts within each group carry their account type', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $type = AccountType::factory()->for($ledger)->create(['name' => 'Savings']);

    Account::factory()->for($ledger)->for($type)->create([
        'include_in_totals' => true,
    ]);

    $this->actingAs($user)
        ->get(route('ledgers.accounts.index', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('accounts.0.accounts.0.account_type.name', 'Savings')
        );
});
```

- [ ] **Step 2: Run new tests to verify they fail**

Run: `php artisan test --compact --filter='accounts index groups by include_in_totals|accounts index omits empty groups|accounts within each group carry their account type'`
Expected: FAIL — response still uses old `type`-based grouping shape

- [ ] **Step 3: Add `groupAccountsByTotals` method to AccountController**

Add after the existing `groupAccountsByType` method (around line 134):

```php
/**
 * Group accounts by include_in_totals for the accounts index page.
 *
 * @return array<int, array{group: string, label: string, accounts: array, total_balance: string}>
 */
private function groupAccountsByTotals($accounts): array
{
    $groups = [
        ['key' => 'included', 'label' => 'Included in totals', 'filter' => true],
        ['key' => 'excluded', 'label' => 'Savings', 'filter' => false],
    ];

    return collect($groups)
        ->map(function ($group) use ($accounts) {
            $filtered = $accounts->where('include_in_totals', $group['filter'])->values();

            if ($filtered->isEmpty()) {
                return null;
            }

            return [
                'group' => $group['key'],
                'label' => $group['label'],
                'accounts' => AccountResource::collection($filtered)->resolve(),
                'total_balance' => number_format(
                    $filtered->sum(fn ($a) => (float) $a->current_balance),
                    2,
                    '.',
                    '',
                ),
            ];
        })
        ->filter()
        ->values()
        ->all();
}
```

- [ ] **Step 4: Wire `groupAccountsByTotals` into `buildGroupedAccounts`**

Replace the `buildGroupedAccounts` method:

```php
private function buildGroupedAccounts(Ledger $ledger): array
{
    $accounts = $ledger->accounts()
        ->with('accountType')
        ->visible()
        ->orderBy('position')
        ->orderBy('name')
        ->get();

    return $this->groupAccountsByTotals($accounts);
}
```

Note: `$accountTypes` is no longer needed by `buildGroupedAccounts`. Update the `index` method — the `$accountTypes` variable is still needed for the `accountTypes` prop, but no longer passed to `buildGroupedAccounts`:

```php
public function index(Ledger $ledger, Request $request): Response
{
    $this->authorize('view', $ledger);

    $accountTypes = $ledger->accountTypes()->orderBy('position')->get();

    return Inertia::render('ledgers/accounts/index', [
        'accounts' => fn () => $this->buildGroupedAccounts($ledger),
        'accountTypes' => fn () => $accountTypes,
        'netWorth' => Inertia::defer(fn () => $this->buildNetWorth($ledger)),
    ]);
}
```

- [ ] **Step 5: Run new grouping tests**

Run: `php artisan test --compact --filter='accounts index groups by include_in_totals|accounts index omits empty groups|accounts within each group carry their account type'`
Expected: PASS

- [ ] **Step 6: Update existing tests that assert old grouping shape**

In `tests/Feature/AccountReorderTest.php`, the test `accounts index renders successfully` (line 33) asserts:

- `->has('accounts', 1)` — still valid (one group)
- `->has('accounts.0.accounts', 2)` — still valid

But the shape changed. Verify: both accounts default to `include_in_totals = true` (factory default), so they'll be in the `included` group. The assertion `->has('accounts', 1)` is still correct. Add a shape assertion:

```php
->where('accounts.0.group', 'included')
```

In `tests/Feature/Ledger/AccountTest.php`, the test `credit card accounts do not include computed statement fields` (from Task 1) asserts `->has('accounts', 1)` and `->has('accounts.0.accounts', 1)`. The credit card account defaults to `include_in_totals = true` (not explicitly set in factory `creditCard()` state, so model default `true` applies). These assertions remain valid.

- [ ] **Step 7: Run full test suite for accounts**

Run: `php artisan test --compact tests/Feature/Ledger/AccountTest.php tests/Feature/Ledger/AccountCrudTest.php tests/Feature/HideAccountTest.php tests/Feature/AccountReorderTest.php`
Expected: All pass

- [ ] **Step 8: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Ledger/AccountController.php tests/Feature/Ledger/AccountTest.php tests/Feature/AccountReorderTest.php
git commit -m "Regroup accounts by include_in_totals instead of account type"
```

---

### Task 3: Frontend — Update types and replace card layout with table/mobile cards

**Files:**

- Modify: `resources/js/pages/ledgers/accounts/index.tsx`

- [ ] **Step 1: Update TypeScript types at top of file**

Replace the `AccountWithStatement` type and `AccountGroup` type (lines 58-71):

```tsx
type AccountGroup = {
    group: 'included' | 'excluded';
    label: string;
    accounts: Account[];
    total_balance: string;
};
```

Remove the `AccountWithStatement` type entirely. All references to `AccountWithStatement` in the file change to `Account`.

Remove unused `EditFormData` fields if applicable (check: `statement_day` and `payment_due_day` are still in the edit form, so `EditFormData` stays the same).

- [ ] **Step 2: Update EditAccountModal prop types**

Change `account: AccountWithStatement` to `account: Account` in the EditAccountModal props (line 425).

- [ ] **Step 3: Update state and handler types**

In `AccountsIndex`:

- Change `editingAccount` state type from `AccountWithStatement | null` to `Account | null` (line 890)
- Change `deletingAccount` state type from `AccountWithStatement | null` to `Account | null` (line 893)
- Update `renderAccountCard` parameter types — the function will be removed entirely in step 5, but first update references

- [ ] **Step 4: Remove the `renderAccountCard` function and credit card statement sub-panel**

Delete the entire `renderAccountCard` function (lines 1025-1213). This removes the card rendering including the credit card statement panel (`isCredit && hasStmt` block).

- [ ] **Step 5: Build the collapsible account group with table (desktop) and cards (mobile)**

Replace the `accountGroups.map(...)` section (lines 1302-1346) and add collapsible group state. Add to the top of `AccountsIndex`:

```tsx
const [collapsedGroups, setCollapsedGroups] = useState<Record<string, boolean>>(
    {
        excluded: true, // Savings collapsed by default
    },
);

function toggleGroup(group: string) {
    setCollapsedGroups((prev) => ({ ...prev, [group]: !prev[group] }));
}
```

Then replace the groups rendering with:

```tsx
{
    accountGroups.map((group) => {
        const isCollapsed = collapsedGroups[group.group] ?? false;
        const totalBalance = parseFloat(group.total_balance);

        return (
            <section key={group.group}>
                {/* Collapsible header */}
                <button
                    type="button"
                    className="flex w-full items-center justify-between rounded-lg px-2 py-2 text-left hover:bg-muted/50"
                    onClick={() => toggleGroup(group.group)}
                >
                    <div className="flex items-center gap-2">
                        <ChevronRight
                            className={`size-4 text-muted-foreground transition-transform ${!isCollapsed ? 'rotate-90' : ''}`}
                        />
                        <span className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                            {group.label}
                        </span>
                    </div>
                    <span
                        className={`text-sm font-semibold tabular-nums ${amountColor(totalBalance)}`}
                    >
                        {formatAbsAmount(totalBalance)}
                    </span>
                </button>

                {!isCollapsed && (
                    <>
                        {/* Desktop table */}
                        <div className="hidden md:block">
                            <table className="mt-1 w-full">
                                <tbody>
                                    {group.accounts.map((account) => {
                                        const balance =
                                            getAccountBalance(account);
                                        const isDragOver =
                                            dragOverId === account.id;

                                        return (
                                            <tr
                                                key={account.id}
                                                draggable
                                                onDragStart={(e) =>
                                                    handleDragStart(
                                                        e,
                                                        account.id,
                                                        group.group,
                                                    )
                                                }
                                                onDragOver={(e) => {
                                                    if (
                                                        dragGroupRef.current ===
                                                        group.group
                                                    ) {
                                                        handleDragOver(
                                                            e,
                                                            account.id,
                                                        );
                                                    }
                                                }}
                                                onDragLeave={handleDragLeave}
                                                onDragEnd={handleDragEnd}
                                                onDrop={(e) => {
                                                    if (
                                                        dragGroupRef.current ===
                                                        group.group
                                                    ) {
                                                        handleDrop(
                                                            e,
                                                            account.id,
                                                            group.accounts,
                                                        );
                                                    }
                                                }}
                                                className={`group border-b border-border/40 transition-colors hover:bg-muted/30 ${
                                                    isDragOver
                                                        ? 'bg-primary/5'
                                                        : ''
                                                }`}
                                            >
                                                <td className="w-8 py-2 pl-2">
                                                    <span className="cursor-grab text-muted-foreground opacity-0 select-none group-hover:opacity-100">
                                                        &#8942;&#8942;
                                                    </span>
                                                </td>
                                                <td className="w-8 py-2">
                                                    <span
                                                        className="inline-block size-2.5 rounded-full"
                                                        style={{
                                                            backgroundColor:
                                                                account.color ??
                                                                '#6B7280',
                                                        }}
                                                    />
                                                </td>
                                                <td className="py-2 pr-4 font-medium">
                                                    {account.name}
                                                </td>
                                                <td className="py-2 pr-4 text-muted-foreground">
                                                    {account.account_type
                                                        ?.name ?? '—'}
                                                </td>
                                                <td
                                                    className={`py-2 pr-4 text-right font-semibold tabular-nums ${amountColor(balance)}`}
                                                >
                                                    {formatAbsAmount(balance)}
                                                </td>
                                                <td className="py-2 pr-4 text-right text-muted-foreground tabular-nums">
                                                    {account.statement_day ??
                                                        '—'}
                                                </td>
                                                <td className="py-2 pr-4 text-right text-muted-foreground tabular-nums">
                                                    {account.payment_due_day ??
                                                        '—'}
                                                </td>
                                                <td className="py-2 pr-2 text-right">
                                                    <div className="flex items-center justify-end gap-0.5">
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                asChild
                                                            >
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="size-7"
                                                                    onClick={() =>
                                                                        setEditingAccount(
                                                                            account,
                                                                        )
                                                                    }
                                                                >
                                                                    <Pencil className="size-3.5" />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                Edit
                                                            </TooltipContent>
                                                        </Tooltip>
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                asChild
                                                            >
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="size-7"
                                                                    asChild
                                                                >
                                                                    <Link
                                                                        href={getTransactionsUrl(
                                                                            account.id,
                                                                        )}
                                                                    >
                                                                        <ExternalLink className="size-3.5" />
                                                                    </Link>
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                Show
                                                                transactions
                                                            </TooltipContent>
                                                        </Tooltip>
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                asChild
                                                            >
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="size-7 text-red-500 hover:text-red-600 dark:text-red-400 dark:hover:text-red-300"
                                                                    onClick={() =>
                                                                        setDeletingAccount(
                                                                            account,
                                                                        )
                                                                    }
                                                                >
                                                                    <Trash2 className="size-3.5" />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                Delete
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        {/* Mobile cards */}
                        <div className="mt-1 space-y-1 md:hidden">
                            {group.accounts.map((account) => {
                                const balance = getAccountBalance(account);
                                const isDragOver = dragOverId === account.id;
                                const isCredit =
                                    account.account_type?.is_credit ?? false;

                                return (
                                    <div
                                        key={account.id}
                                        draggable
                                        onDragStart={(e) =>
                                            handleDragStart(
                                                e,
                                                account.id,
                                                group.group,
                                            )
                                        }
                                        onDragOver={(e) => {
                                            if (
                                                dragGroupRef.current ===
                                                group.group
                                            ) {
                                                handleDragOver(e, account.id);
                                            }
                                        }}
                                        onDragLeave={handleDragLeave}
                                        onDragEnd={handleDragEnd}
                                        onDrop={(e) => {
                                            if (
                                                dragGroupRef.current ===
                                                group.group
                                            ) {
                                                handleDrop(
                                                    e,
                                                    account.id,
                                                    group.accounts,
                                                );
                                            }
                                        }}
                                        className={`rounded-lg px-2 py-1.5 transition-colors ${isDragOver ? 'bg-primary/5' : ''}`}
                                    >
                                        <div className="flex items-center gap-2">
                                            <span className="cursor-grab text-sm text-muted-foreground select-none">
                                                &#8942;&#8942;
                                            </span>
                                            <span
                                                className="inline-block size-2.5 rounded-full"
                                                style={{
                                                    backgroundColor:
                                                        account.color ??
                                                        '#6B7280',
                                                }}
                                            />
                                            <span className="flex-1 truncate text-sm font-medium">
                                                {account.name}
                                            </span>
                                            <span
                                                className={`text-sm font-semibold tabular-nums ${amountColor(balance)}`}
                                            >
                                                {formatAbsAmount(balance)}
                                            </span>
                                        </div>
                                        <div className="mt-0.5 flex items-center gap-2 pl-9">
                                            <span className="flex-1 truncate text-xs text-muted-foreground">
                                                {account.account_type?.name ??
                                                    '—'}
                                                {isCredit &&
                                                    account.statement_day !=
                                                        null &&
                                                    ` · Stmt ${account.statement_day}`}
                                                {isCredit &&
                                                    account.payment_due_day !=
                                                        null &&
                                                    ` · Due ${account.payment_due_day}`}
                                            </span>
                                            <div className="flex items-center gap-0.5">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-6"
                                                    onClick={() =>
                                                        setEditingAccount(
                                                            account,
                                                        )
                                                    }
                                                >
                                                    <Pencil className="size-3" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-6"
                                                    asChild
                                                >
                                                    <Link
                                                        href={getTransactionsUrl(
                                                            account.id,
                                                        )}
                                                    >
                                                        <ExternalLink className="size-3" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-6 text-red-500 hover:text-red-600 dark:text-red-400 dark:hover:text-red-300"
                                                    onClick={() =>
                                                        setDeletingAccount(
                                                            account,
                                                        )
                                                    }
                                                >
                                                    <Trash2 className="size-3" />
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </>
                )}
            </section>
        );
    });
}
```

- [ ] **Step 6: Update drag-and-drop state to use group key instead of typeId**

Replace `dragTypeId` state and `dragOverIdRef` with group-based equivalents:

```tsx
const dragOverIdRef = useRef<number | null>(null);
const [dragOverId, setDragOverId] = useState<number | null>(null);
const dragGroupRef = useRef<string | null>(null);
const isReorderingRef = useRef(false);
```

Update `handleDragStart`:

```tsx
function handleDragStart(
    e: React.DragEvent,
    accountId: number,
    groupKey: string,
) {
    e.dataTransfer.setData('text/plain', String(accountId));
    e.dataTransfer.effectAllowed = 'move';
    dragGroupRef.current = groupKey;
}
```

Update `handleDragEnd`:

```tsx
function handleDragEnd() {
    dragOverIdRef.current = null;
    setDragOverId(null);
    dragGroupRef.current = null;
}
```

Update `handleDrop` — the signature stays the same (receives `targetId` and `typeAccounts` which is now just the group's accounts array). Remove `setDragTypeId(null)` and replace with `dragGroupRef.current = null`.

Remove the `dragTypeId` state and `setDragTypeId` entirely.

- [ ] **Step 7: Add `ChevronRight` to lucide-react imports**

Add `ChevronRight` to the import from `lucide-react` (line 2-9).

- [ ] **Step 8: Clean up unused imports**

Remove imports that are no longer used:

- `Card`, `CardContent` from `@/components/ui/card` (no longer used in main page — check if `NetWorthCards` still uses them: yes it does, so keep)
- `Badge` from `@/components/ui/badge` (check: used in premium badge, keep)
- `formatDate` from `@/lib/format` (no longer used — statement dates removed)
- `AlertTriangle` from lucide-react (was used for negative balance tooltip in cards — check if still needed: no, remove)

- [ ] **Step 9: Run `npm run lint` and fix issues**

Run: `npm run lint`
Expected: Fix any lint/format issues

- [ ] **Step 10: Run `vendor/bin/pint --dirty --format agent`**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 11: Verify the page renders**

Run: `php artisan test --compact --filter='account index renders successfully'`
Expected: PASS (this test only checks the Inertia component renders)

- [ ] **Step 12: Commit**

```bash
git add resources/js/pages/ledgers/accounts/index.tsx
git commit -m "Replace accounts card view with table (desktop) and compact cards (mobile)"
```

---

### Task 4: Final verification

- [ ] **Step 1: Run all account-related tests**

Run: `php artisan test --compact tests/Feature/Ledger/AccountTest.php tests/Feature/Ledger/AccountCrudTest.php tests/Feature/HideAccountTest.php tests/Feature/AccountReorderTest.php tests/Feature/Ledger/AccountExportTest.php`
Expected: All pass

- [ ] **Step 2: Run full test suite**

Run: `php artisan test --compact`
Expected: All pass (no regressions from dashboard or other pages using `groupAccountsByType`)

- [ ] **Step 3: Run lint and format**

Run: `npm run lint && vendor/bin/pint --dirty --format agent`
Expected: Clean

- [ ] **Step 4: Final commit if any lint fixes**

Only if lint/pint made changes:

```bash
git add -A
git commit -m "Fix lint and formatting"
```
