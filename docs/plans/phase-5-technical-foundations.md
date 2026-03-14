# Phase 5 — Technical Foundations: Implementation Plan

> **Goal**: Improve reliability, performance, and extensibility. Invisible to users but critical for growth.
>
> **Prerequisites**: Core features stable (Phases 1-3). Can run in parallel with Phase 4.
>
> **Estimated scope**: 6 tasks

---

## Task 5.1 — Database Performance (Indexes)

**Priority**: Medium
**Effort**: Small

### Current State
- Some indexes may already exist (check existing migrations)
- As transaction count grows, queries will slow without proper indexes

### Implementation Steps

1. **Audit existing indexes** — Check all migrations for existing index definitions.
2. **Add missing indexes** — Create a new migration adding indexes for:
   ```php
   // Transactions — most queried table
   Schema::table('transactions', function (Blueprint $table) {
       $table->index(['ledger_id', 'transaction_date']);     // Date-range queries
       $table->index(['ledger_id', 'account_id']);           // Account filter
       $table->index(['ledger_id', 'category_id']);          // Category filter
       $table->index(['ledger_id', 'payee_id']);             // Payee filter
       $table->index(['ledger_id', 'type']);                 // Type filter
       $table->index(['description']);                        // Text search
   });

   // Bills
   Schema::table('bills', function (Blueprint $table) {
       $table->index(['ledger_id', 'next_due_date']);        // Upcoming bills
       $table->index(['ledger_id', 'is_active']);            // Active bills
   });

   // Categories
   Schema::table('categories', function (Blueprint $table) {
       $table->index(['ledger_id', 'parent_id']);            // Hierarchical queries
       $table->index(['ledger_id', 'type']);                 // Expense/income filter
   });

   // Budgets
   Schema::table('budgets', function (Blueprint $table) {
       $table->index(['ledger_id', 'category_id']);          // Category aggregation
   });
   ```
3. **Verify with EXPLAIN** — Run `EXPLAIN ANALYZE` on the slowest queries to confirm indexes are used.

### Files to Modify
- New migration: `xxxx_add_performance_indexes.php`

### Testing
- Feature test: existing tests still pass after migration
- Performance: verify query plans use indexes (manual check)

---

## Task 5.2 — Soft Deletes (Undo/Recover)

**Priority**: Medium
**Effort**: Medium

### Current State
- Check if `SoftDeletes` trait is already used on models (some migrations may already have `softDeletes()`)
- Currently deletions may be permanent

### Implementation Steps

1. **Audit existing models** — Check which models already use `SoftDeletes`.
2. **Add soft deletes to remaining models**:
   - `Transaction`, `Account`, `Bill`, `Category`, `Payee`, `Budget`, `Tag`
   - Migration: `$table->softDeletes()` for each table missing it
   - Model: `use SoftDeletes;` trait
3. **"Recently Deleted" section** — Trash page per entity type:
   - `/ledgers/{ledger}/trash` — Lists all soft-deleted items grouped by type
   - Restore and permanent delete actions
   - Auto-purge after 30 days (scheduled command)
4. **Update delete flows** — Existing delete operations now soft-delete. Undo toast (from Phase 1) can call restore within 5 seconds.
5. **Scheduled cleanup** — Artisan command: `php artisan trash:purge` — permanently deletes items older than 30 days. Register in scheduler.

### Files to Modify
- New migration: `xxxx_add_soft_deletes.php` (for tables missing it)
- `app/Models/*.php` — Add `SoftDeletes` trait where missing
- `app/Http/Controllers/Ledger/TrashController.php` — New controller
- `resources/js/pages/ledgers/trash/index.tsx` — Trash UI
- `app/Console/Commands/PurgeTrash.php` — New command
- `routes/console.php` — Schedule purge
- `routes/ledger.php` — Trash routes

### Testing
- Feature test: delete transaction, verify soft-deleted (not gone)
- Feature test: restore soft-deleted transaction
- Feature test: permanent delete after 30 days
- Feature test: existing functionality still works with soft deletes

---

## Task 5.3 — Activity/Audit Log

**Priority**: Low-Medium
**Effort**: Medium

### Current State
- `ActivityLog` model exists
- `ActivityLogService` exists
- `ActivityLogController` exists
- May already be partially implemented

### Implementation Steps

1. **Verify existing implementation** — Check what's already tracked.
2. **Expand coverage** — Ensure all CRUD operations are logged:
   - Transaction create/update/delete (with old + new values)
   - Account changes
   - Bill payments
   - Category changes
   - Budget changes
3. **Use model observers** — If not already using them, create observers in `app/Observers/` for each model:
   ```php
   class TransactionObserver {
       public function updated(Transaction $transaction): void {
           ActivityLogService::log('updated', $transaction, $transaction->getChanges(), $transaction->getOriginal());
       }
   }
   ```
4. **History UI** — Show activity log:
   - Transaction detail: "History" tab showing all changes
   - Global activity feed (optional)
5. **Retention** — Keep logs for 90 days, then archive/delete.

### Files to Modify
- `app/Observers/*.php` — Model observers (new or expand)
- `app/Services/ActivityLogService.php` — Verify/expand logging
- `app/Http/Controllers/Ledger/ActivityLogController.php` — API endpoints
- Transaction detail component — History section

### Testing
- Feature test: update transaction, verify activity log entry created with old/new values
- Feature test: delete transaction, verify log entry

---

## Task 5.4 — REST API

**Priority**: Low
**Effort**: Large

### Implementation Steps

1. **Create API routes** — `routes/api.php` with versioned prefix: `/api/v1/`
2. **Authentication** — Use Laravel Sanctum for API token authentication:
   - Token management UI in Settings
   - `php artisan install:api` if not already done
3. **API Resources** — Create Eloquent API Resources for each model:
   - `app/Http/Resources/TransactionResource.php`
   - `app/Http/Resources/AccountResource.php`
   - etc.
4. **API Controllers** — Create separate API controllers or reuse existing with content negotiation:
   - `app/Http/Controllers/Api/V1/TransactionController.php`
   - Standard REST endpoints: index, show, store, update, destroy
5. **Pagination** — Use Laravel's built-in pagination with API resource wrapping.
6. **Rate limiting** — Configure in `bootstrap/app.php` middleware.
7. **API documentation** — Auto-generate with Scribe or similar.

### Files to Modify
- `routes/api.php` — API routes
- `app/Http/Controllers/Api/V1/*.php` — API controllers
- `app/Http/Resources/*.php` — API resources
- `bootstrap/app.php` — API middleware/rate limiting
- `resources/js/pages/settings/*.tsx` — API token management UI
- `config/sanctum.php` — Token configuration

### Testing
- Feature test: CRUD via API endpoints with token auth
- Feature test: rate limiting works
- Feature test: API resources return correct format

---

## Task 5.5 — Transaction Amount Guardrails

**Priority**: Medium
**Effort**: Small

### Implementation Steps

1. **Backend validation** — In `StoreTransactionRequest` and `UpdateTransactionRequest`:
   ```php
   'amount' => ['required', 'numeric', 'gt:0'],
   ```
   Custom message: "Please enter an amount greater than zero."
2. **Frontend prevention** — In the transaction form, set `min="0.01"` on the amount input and prevent negative values via `onChange` handler.
3. **Split amounts** — Same validation for split transaction amounts.

### Files to Modify
- `app/Http/Requests/StoreTransactionRequest.php` — Add `gt:0` rule
- `app/Http/Requests/UpdateTransactionRequest.php` — Add `gt:0` rule
- `resources/js/components/add-transaction-modal.tsx` — Frontend validation

### Testing
- Feature test: submit zero amount, assert validation error
- Feature test: submit negative amount, assert validation error
- Feature test: submit valid positive amount, assert accepted

---

## Task 5.6 — Automated Test Coverage

**Priority**: Medium
**Effort**: Large

### Current State
- 20+ test files exist in `tests/Feature/` and `tests/Unit/`
- Pest v4 framework configured
- Coverage varies by feature

### Implementation Steps

1. **Audit current coverage** — Run `php artisan test --coverage` to see current state.
2. **Identify gaps** — Focus on critical paths:
   - Transaction CRUD (all three types: expense, income, transfer)
   - Transfer pair management (create, edit, delete paired transactions)
   - Bill payment and schedule advancement
   - Auto-bill processing
   - Budget calculations (spent amount, thresholds)
   - Cycle boundary computation
   - Import/export
   - Category hierarchy operations
   - Payee merge
3. **Write missing tests** — Prioritize by risk:
   - **High risk**: Transaction operations, bill payments, budget calculations
   - **Medium risk**: Import/export, category operations
   - **Low risk**: UI-centric features, settings
4. **Target**: 80%+ code coverage on backend (`app/` directory)
5. **CI integration** — Ensure tests run on every push (if CI exists)

### Files to Modify
- `tests/Feature/Ledger/*.php` — Expand existing tests
- New test files for untested features
- `phpunit.xml` — Ensure coverage reporting configured

### Testing Meta
- Run full suite: `php artisan test --compact`
- Coverage report: `php artisan test --coverage --min=80`

---

## Implementation Order

| Order | Task | Effort | Dependencies |
|-------|------|--------|-------------|
| 1 | 5.5 Amount guardrails | Small | None |
| 2 | 5.1 Database indexes | Small | None |
| 3 | 5.6 Test coverage | Large | Should run alongside all other work |
| 4 | 5.2 Soft deletes | Medium | None |
| 5 | 5.3 Activity log | Medium | Verify existing implementation |
| 6 | 5.4 REST API | Large | All CRUD operations stable |

**Parallelization**: 5.1, 5.5, and 5.6 can start immediately in parallel. 5.2 and 5.3 are independent. 5.4 should be last as it wraps existing functionality.

**Note**: Task 5.6 (test coverage) should be treated as an ongoing effort that runs alongside all other phases, not just Phase 5. Write tests as you implement features in Phases 1-4.
