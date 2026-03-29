# Accounts Page Table Refactor

## Summary

Replace the card-based accounts page with a table layout, regroup accounts by include/exclude in totals (instead of account type), add collapsible group headers with totals, and remove the statement balance feature for credit cards.

## Current State

- **Layout**: Card grid (1-3 columns depending on viewport) grouped by account type (Savings, Credit Card, Cash, etc.)
- **Grouping**: By `account_type_id` — each group has a color-dot header and a total footer
- **Credit cards**: Backend computes `statement_balance`, `current_spending`, `outstanding`, `payment_due_date` per billing cycle; frontend renders a sub-panel inside each credit card's card
- **Drag-and-drop**: HTML5 drag API, reorder within same account type group
- **Key files**:
    - `resources/js/pages/ledgers/accounts/index.tsx` (1411 lines — page component, modals, card renderer)
    - `app/Http/Controllers/Ledger/AccountController.php` (316 lines — index, buildGroupedAccounts, buildNetWorth, CRUD, reorder, adjustBalance, export)
    - `app/Http/Resources/AccountResource.php` (serializes account + conditional statement fields)

## Changes

### Backend

#### 1. Remove statement balance computation

In `AccountController::buildGroupedAccounts()`, remove the entire `$accounts->map()` block (lines 50-95) that computes statement cycle data. This eliminates per-credit-card queries for `statement_balance`, `current_spending`, `outstanding`, and `payment_due_date`.

The raw `statement_day` and `payment_due_day` integers remain on the model and are serialized as-is.

#### 2. Regroup by `include_in_totals`

Replace `groupAccountsByType()` with a new grouping method that splits accounts into two groups:

- **"Included in totals"** — accounts where `include_in_totals = true`
- **"Savings"** — accounts where `include_in_totals = false`

New response shape for the `accounts` prop:

```php
[
    [
        'group' => 'included',
        'label' => 'Included in totals',
        'accounts' => AccountResource::collection(...),
        'total_balance' => '12345.67',
    ],
    [
        'group' => 'excluded',
        'label' => 'Savings',
        'accounts' => AccountResource::collection(...),
        'total_balance' => '50000.00',
    ],
]
```

Groups with zero accounts are omitted. Accounts within each group are ordered by `position`, then `name`. Each account still carries its `account_type` relationship (eager-loaded) so the type name can be displayed as a table column.

#### 3. Trim AccountResource

Remove conditional fields: `statement_start`, `statement_end`, `statement_balance`, `current_spending`, `outstanding`, `payment_due_date`.

Keep: `id`, `ledger_id`, `account_type_id`, `name`, `initial_balance`, `current_balance`, `statement_day`, `payment_due_day`, `color`, `is_hidden`, `position`, `include_in_totals`, `account_type` (when loaded), `created_at`, `updated_at`.

### Frontend

#### 4. Desktop table layout (md+)

Replace the card grid with a `<table>` inside each collapsible group.

Columns:

| Column      | Content                                      | Alignment |
| ----------- | -------------------------------------------- | --------- |
| Drag handle | Grip dots icon, visible on hover             | Left      |
| Color       | Account color dot                            | Left      |
| Name        | Account name, font-medium                    | Left      |
| Type        | Account type name, muted text                | Left      |
| Balance     | `formatAbsAmount(balance)`, colored by sign  | Right     |
| Stmt Day    | Raw integer or dash                          | Right     |
| Due Day     | Raw integer or dash                          | Right     |
| Actions     | Edit, view transactions, delete icon buttons | Right     |

Standard table padding. No outer card wrapper — the table rows themselves provide structure.

#### 5. Mobile card layout (<md)

Compact cards with tight padding (py-1.5, text-sm):

- **Line 1**: Drag handle, color dot, account name, balance (right-aligned)
- **Line 2**: Account type name + optional "Stmt {day} / Due {day}" for credit cards, action icons (right-aligned)

#### 6. Collapsible groups

Each group has a clickable header row:

- Left side: chevron icon (rotates on toggle) + group label
- Right side: group total balance (colored by sign)

Default state:

- "Included in totals" — **expanded**
- "Savings" — **collapsed**

Empty groups are not rendered.

#### 7. Drag-and-drop

Reuse existing HTML5 drag API approach, adapted for table rows / mobile cards. Drag within group only — accounts cannot be moved between the included and excluded groups.

The reorder API endpoint (`POST /ledgers/{ledger}/accounts/reorder`) stays the same.

#### 8. Edit modal cleanup

Remove the statement balance / outstanding display section from `EditAccountModal`. The `statement_day` and `payment_due_day` form fields remain (shown when account type `is_credit`).

### TypeScript types

Update `AccountWithStatement` type — remove `statement_balance`, `statement_start`, `statement_end`, `current_spending`, `payment_due_date`, `outstanding`. Or simplify to just use `Account` directly since no extra statement fields exist.

Update `AccountGroup` type to reflect the new shape:

```ts
type AccountGroup = {
    group: 'included' | 'excluded';
    label: string;
    accounts: Account[];
    total_balance?: string;
};
```

### What stays unchanged

- Net worth cards (deferred prop, same component)
- `CreateAccountModal` (same fields, same behavior)
- Delete confirmation dialog
- Premium gating logic (7-account limit)
- `buildNetWorth()` method
- `reorder()`, `adjustBalance()`, `export()` controller methods
- Database schema (no migrations)

## Testing

- Update existing accounts page feature tests to assert new grouping structure
- Test that included accounts appear in the "included" group and excluded in "excluded"
- Test that statement computation fields are no longer in the response
- Test reorder still works within the new grouping
- Test empty groups are omitted
