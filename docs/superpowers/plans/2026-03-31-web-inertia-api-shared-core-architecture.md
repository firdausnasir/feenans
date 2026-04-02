# Web Inertia + API Shared Core Architecture Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Roll the application from mixed controller/FormRequest patterns into a phased architecture where web remains fully Inertia-driven, API serves first-party non-browser clients via Sanctum tokens, and both surfaces share query actions, use-case actions, domain exceptions, and output data contracts.

**Architecture:** The rollout is sequential and dependency-driven. Phase 0 aligns project rules, dependency approval, and rollout scope, Phase 1 proves the Data-first request pipeline and shared exception translation, Phase 2 establishes the shared-core scaffolding, Phase 3 ships the token auth surface, Phase 4 migrates one pilot module end-to-end, and later phases scale the pattern across more modules and optional tooling. No later phase should begin until the prior phase's verification gates pass.

**Tech Stack:** Laravel 12, Inertia v2, React 19, Sanctum 4, Fortify 1, Wayfinder, Pest 4, Spatie Laravel Data, Spatie TypeScript Transformer, optional Spatie Laravel Query Builder

---

## File Map

| Action | File                                                     | Responsibility                                                                     |
| ------ | -------------------------------------------------------- | ---------------------------------------------------------------------------------- |
| Modify | `AGENTS.md`                                              | Update frontend architecture rule to allow pure-Inertia web and separate API usage |
| Modify | `composer.json`                                          | Add Spatie packages and any required scripts                                       |
| Modify | `bootstrap/providers.php`                                | Register generated provider(s) from TypeScript transformer install                 |
| Modify | `bootstrap/app.php`                                      | Centralize domain exception translation and API/web response handling              |
| Modify | `routes/api.php`                                         | Add first-party token auth routes and pilot module API routes                      |
| Modify | `routes/ledger.php`                                      | Point pilot web module to new Web controller/actions/data classes                  |
| Create | `app/Data/Shared/Input/...`                              | Base input data conventions and helpers                                            |
| Create | `app/Data/Shared/Output/...`                             | Shared output contract helpers                                                     |
| Create | `app/Exceptions/Domain/...`                              | Typed domain exceptions and codes                                                  |
| Create | `app/Http/Controllers/Web/...`                           | Web transport controllers for migrated modules                                     |
| Create | `app/Http/Controllers/Api/V1/...`                        | API transport controllers for migrated modules and token auth                      |
| Create | `app/Actions/.../Queries/...`                            | Read-side query actions and page/endpoint query composers                          |
| Create | `app/Actions/.../UseCases/...`                           | Top-level use-case actions and internal helper actions                             |
| Create | `app/Data/Tags/Input/...`                                | Pilot module request data classes                                                  |
| Create | `app/Data/Tags/Output/...`                               | Pilot module output data classes                                                   |
| Modify | `app/Http/Controllers/Ledger/TagController.php`          | Replace legacy FormRequest/controller logic during pilot migration                 |
| Modify | `resources/js/pages/ledgers/tags/index.tsx`              | Consume new page output contract while remaining pure Inertia                      |
| Modify | `resources/js/types/index.ts`                            | Bridge generated TS output types if adopted                                        |
| Modify | `resources/js/types/ledger.ts`                           | Remove or narrow manual pilot module types if generated types replace them         |
| Modify | `app/Providers/AppServiceProvider.php`                   | Add shared bindings/rate limit refinements if needed                               |
| Create | `app/Providers/TypeScriptTransformerServiceProvider.php` | Configure generated TS types from Data output contracts                            |
| Create | `tests/Feature/Architecture/...`                         | Request pipeline, exception translation, and token auth proof tests                |
| Modify | `tests/Feature/Ledger/TagTest.php`                       | Pilot web route coverage against new Data/action architecture                      |
| Create | `tests/Feature/Api/V1/...`                               | Pilot API route coverage and token auth coverage                                   |

---

## Phase Overview

1. **Phase 0: Governance, Approval, And Scope Alignment**
2. **Phase 1: Architecture Proof Spike**
3. **Phase 2: Shared-Core Scaffolding**
4. **Phase 3: Token Auth Surface**
5. **Phase 4: Pilot Module Migration (Tags)**
6. **Phase 5: Extend Pattern To Adjacent Modules**
7. **Phase 6: Migrate Complex Modules**
8. **Phase 7: Optional Contract Tooling And Cleanup**

Phases 0 through 6 depend on the previous phase and should not overlap. Phase 7 is optional and stays last.

---

### Phase 0: Governance, Approval, And Scope Alignment

**Outcome:** The repo rules match the approved architecture, and the rollout sequence is codified before touching app code.

**Files:**

- Modify: `AGENTS.md`
- Modify: `docs/superpowers/specs/2026-03-31-web-inertia-api-shared-core-architecture-design.md` only if a clarification discovered during implementation needs to be backported
- Test: none beyond manual review of rule text

- [ ] **Step 1: Update the frontend architecture rule in `AGENTS.md`**

Change the rule that says all new frontend features must fetch backend APIs so it becomes:

- browser-facing product pages use web/Inertia routes by default
- `/api/*` is for non-browser first-party clients unless explicitly stated otherwise

- [ ] **Step 2: Add one short rule for admin exception status**

Document whether admin remains a temporary exception or whether admin is expected to follow the same pure-Inertia browser rule during the rollout.

- [ ] **Step 3: Record explicit approval for new dependencies and new base folders**

Because the current repo rules forbid both without approval, record in `AGENTS.md` that this rollout is approved to:

- add `spatie/laravel-data`
- add `spatie/laravel-typescript-transformer` later if Phase 7 is reached
- add `spatie/laravel-query-builder` later only if explicitly chosen in Phase 7
- create new top-level subdirectories under `app/` such as `app/Data`, `app/Actions`, and `app/Exceptions/Domain`

- [ ] **Step 4: Review the updated rules against the approved spec**

Confirm the governance text does not conflict with:

- pure Inertia web surface
- first-party token API
- Fortify temporary exception

- [ ] **Step 5: Commit governance alignment**

```bash
git add AGENTS.md docs/superpowers/specs/2026-03-31-web-inertia-api-shared-core-architecture-design.md
git commit -m "Align project rules with shared-core architecture rollout"
```

**Dependency gate for next phase:** Rules must no longer contradict the approved architecture, and dependency/folder approvals must be explicit.

---

### Phase 1: Architecture Proof Spike

**Outcome:** Prove that `spatie/laravel-data` can replace FormRequests cleanly for at least one realistic web/API request path without breaking Laravel/Inertia behavior.

**Files:**

- Modify: `composer.json`
- Modify: `bootstrap/providers.php`
- Create: `app/Data/Architecture/Input/ProofTagData.php`
- Create: `app/Http/Controllers/Web/Architecture/ProofTagController.php`
- Create: `app/Http/Controllers/Api/V1/Architecture/ProofTagController.php`
- Create: `routes/api.php` test route section or temporary architecture test routes if preferred
- Create: `routes/ledger.php` temporary proof route section or test-only route provider if preferred
- Create: `tests/Feature/Architecture/DataRequestPipelineTest.php`
- Create: `tests/Feature/Architecture/InertiaExceptionTranslationTest.php`

- [ ] **Step 1: Install `spatie/laravel-data` as a failing-architecture prerequisite**

Run:

```bash
composer require spatie/laravel-data
php artisan vendor:publish --provider="Spatie\LaravelData\LaravelDataServiceProvider" --tag="data-config" --no-interaction
```

- [ ] **Step 2: Write failing feature tests for the request pipeline proof**

Create `tests/Feature/Architecture/DataRequestPipelineTest.php` covering:

- injected Data validation returns web session errors on an Inertia form-style request
- injected Data validation returns JSON `422` on an API request
- injected Data authorization denies based on ledger policy
- injected Data can read route model and current user
- custom validation messages still appear
- file upload payloads still validate correctly

- [ ] **Step 3: Write a failing feature test for Inertia GET-like domain failure translation**

Create `tests/Feature/Architecture/InertiaExceptionTranslationTest.php` covering:

- initial page GET domain exception uses the centralized Inertia exception behavior
- deferred or partial reload style request does not fall back to an ad hoc JSON error envelope

- [ ] **Step 4: Run only the new architecture proof tests and confirm failure**

Run:

```bash
php artisan test --compact tests/Feature/Architecture/DataRequestPipelineTest.php tests/Feature/Architecture/InertiaExceptionTranslationTest.php tests/Feature/ExceptionRenderingTest.php
```

Expected: FAIL because the proof classes and translation hooks do not exist yet.

- [ ] **Step 5: Implement a minimal proof request Data class**

Create `app/Data/Architecture/Input/ProofTagData.php` that demonstrates:

- `authorize()` with injected ledger route model and current user context
- validation rules/messages
- redirect or error bag customization if required by the tests
- route parameter injection with Spatie attributes

- [ ] **Step 6: Implement minimal proof controllers and temporary proof routes**

Create one web proof controller and one API proof controller that inject the proof Data class directly and return minimal success responses.

- [ ] **Step 7: Adjust exception handling only enough to make the proof tests pass**

Modify `bootstrap/app.php` so domain-style proof exceptions map correctly for:

- initial web GET
- API JSON requests

Do not generalize beyond what the tests prove yet.

- [ ] **Step 8: Run the proof tests again and make them pass**

Run:

```bash
php artisan test --compact tests/Feature/Architecture/DataRequestPipelineTest.php tests/Feature/Architecture/InertiaExceptionTranslationTest.php tests/Feature/ExceptionRenderingTest.php
```

- [ ] **Step 9: Decide go/no-go based on proof results**

If any critical behavior cannot be preserved cleanly, document the explicit FormRequest exception path now and stop the Data-first migration for those cases.

- [ ] **Step 10: Run Pint and commit the proof spike**

Run:

```bash
vendor/bin/pint --dirty --format agent
```

Commit:

```bash
git add composer.json bootstrap/providers.php bootstrap/app.php app/Data/Architecture app/Http/Controllers/Web/Architecture app/Http/Controllers/Api/V1/Architecture routes/api.php routes/ledger.php tests/Feature/Architecture
git commit -m "Prove Data-first request pipeline for web and API"
```

**Dependency gate for next phase:** The proof tests must pass. If not, do not proceed.

---

### Phase 2: Shared-Core Scaffolding

**Outcome:** Establish the permanent shared-core folders, base classes, and exception translation model used by all later module migrations.

**Files:**

- Create: `app/Data/Shared/Input/BaseInputData.php`
- Create: `app/Data/Shared/Output/BaseOutputData.php`
- Create: `app/Exceptions/Domain/DomainException.php`
- Create: `app/Exceptions/Domain/DomainConflict.php`
- Create: `app/Exceptions/Domain/DomainNotAllowed.php`
- Create: `app/Actions/Shared/Queries/...` if a common read abstraction is needed
- Create: `app/Actions/Shared/UseCases/...` only if a common base is justified
- Modify: `bootstrap/app.php`
- Modify: `app/Providers/AppServiceProvider.php` only if bindings/helpers are truly needed
- Create: `tests/Feature/Architecture/DomainExceptionTranslationTest.php`

- [ ] **Step 1: Write failing tests for domain exception translation**

Create `tests/Feature/Architecture/DomainExceptionTranslationTest.php` covering:

- web mutation request maps a domain exception to redirect + flash
- web GET maps a domain exception to centralized Inertia error handling
- API request maps a domain exception to structured JSON `4xx`
- existing normal 404 rendering for web and API still matches `tests/Feature/ExceptionRenderingTest.php`

- [ ] **Step 2: Write the base domain exception classes**

Implement a base `DomainException` shape with:

- stable code
- safe message
- optional context payload

Only add subclasses needed by the tests.

- [ ] **Step 3: Create base input and output Data classes**

The base input class should centralize only conventions that are already proven useful:

- common helper access to route/user context
- any shared error bag or redirect policy defaults

The base output class should remain light and avoid coupling to one module.

- [ ] **Step 4: Centralize exception translation in `bootstrap/app.php`**

Extend the exception handling so typed domain exceptions map differently for:

- Inertia mutation requests
- Inertia GET-like requests
- JSON API requests

- [ ] **Step 5: Run the architecture translation test suite and make it pass**

Run:

```bash
php artisan test --compact tests/Feature/Architecture/DomainExceptionTranslationTest.php tests/Feature/Architecture/InertiaExceptionTranslationTest.php tests/Feature/ExceptionRenderingTest.php
```

- [ ] **Step 6: Run Pint and commit the shared-core scaffold**

Run:

```bash
vendor/bin/pint --dirty --format agent
```

Commit:

```bash
git add app/Data/Shared app/Exceptions/Domain bootstrap/app.php app/Providers/AppServiceProvider.php tests/Feature/Architecture
git commit -m "Add shared Data and domain exception scaffolding"
```

**Dependency gate for next phase:** Shared exception translation and base Data conventions must be stable and tested.

---

### Phase 3: Token Auth Surface

**Outcome:** Create the first-party token authentication surface outside Fortify, with clear issuance and revocation behavior.

**Files:**

- Create: `app/Data/Auth/Input/CreateApiTokenData.php`
- Create: `app/Data/Auth/Output/ApiTokenData.php`
- Create: `app/Actions/Auth/UseCases/CreateApiTokenAction.php`
- Create: `app/Actions/Auth/UseCases/RevokeApiTokenAction.php`
- Create: `app/Http/Controllers/Api/V1/Auth/ApiTokenController.php`
- Modify: `routes/api.php`
- Modify: `config/sanctum.php` only if needed for token abilities or guard clarification
- Create: `tests/Feature/Api/V1/Auth/ApiTokenTest.php`

- [ ] **Step 1: Write failing API token tests**

Create `tests/Feature/Api/V1/Auth/ApiTokenTest.php` covering:

- authenticated web user can create a named first-party token through the dedicated token endpoint
- token creation returns plain token string once plus token metadata
- token can be revoked individually
- unauthenticated requests are rejected

- [ ] **Step 2: Run the token tests and confirm failure**

Run:

```bash
php artisan test --compact tests/Feature/Api/V1/Auth/ApiTokenTest.php
```

- [ ] **Step 3: Implement token input/output Data and use-case actions**

Keep the first version minimal:

- device name required
- coarse first-party abilities only if needed now
- issue token
- revoke token by id or current token selection

- [ ] **Step 4: Add API token controller and routes**

Add routes in `routes/api.php` under an auth-protected prefix, keeping this surface outside Fortify.

- [ ] **Step 5: Run the token tests again and make them pass**

Run:

```bash
php artisan test --compact tests/Feature/Api/V1/Auth/ApiTokenTest.php
```

- [ ] **Step 6: Run Pint and commit the token auth surface**

Run:

```bash
vendor/bin/pint --dirty --format agent
```

Commit:

```bash
git add app/Data/Auth app/Actions/Auth app/Http/Controllers/Api/V1/Auth routes/api.php config/sanctum.php tests/Feature/Api/V1/Auth/ApiTokenTest.php
git commit -m "Add first-party Sanctum token management endpoints"
```

**Dependency gate for next phase:** Token issuance and revocation tests must pass before any client-facing API module ships.

---

### Phase 4: Pilot Module Migration (Tags)

**Outcome:** Migrate one small ledger module end-to-end on both web and API surfaces using the new architecture.

**Why Tags:**

- already has deferred web read behavior
- currently uses a small `FormRequest`
- CRUD is simple enough to prove the pattern without transaction/import complexity
- existing tests already cover web behavior well

**Files:**

- Modify: `app/Http/Controllers/Ledger/TagController.php`
- Create: `app/Http/Controllers/Api/V1/Ledger/TagController.php`
- Create: `app/Data/Tags/Input/StoreTagData.php`
- Create: `app/Data/Tags/Input/UpdateTagData.php`
- Create: `app/Data/Tags/Output/TagData.php`
- Create: `app/Data/Tags/Output/TagPageData.php`
- Create: `app/Actions/Tags/Queries/GetTagPageQuery.php`
- Create: `app/Actions/Tags/Queries/ListTagsQuery.php`
- Create: `app/Actions/Tags/UseCases/StoreTagAction.php`
- Create: `app/Actions/Tags/UseCases/UpdateTagAction.php`
- Create: `app/Actions/Tags/UseCases/DeleteTagAction.php`
- Modify: `routes/ledger.php`
- Modify: `routes/api.php`
- Modify: `resources/js/pages/ledgers/tags/index.tsx`
- Modify: `tests/Feature/Ledger/TagTest.php`
- Create: `tests/Feature/Api/V1/Ledger/TagApiTest.php`

- [ ] **Step 1: Add failing web tests for Data/action-backed tag behavior**

Extend `tests/Feature/Ledger/TagTest.php` to cover:

- web CRUD still redirects correctly
- deferred `tags` payload still reloads correctly
- validation messages still match the old `TagRequest` behavior
- authorization still respects the ledger policy

- [ ] **Step 2: Add failing API tests for tag CRUD**

Create `tests/Feature/Api/V1/Ledger/TagApiTest.php` covering:

- list tags with token auth
- create tag with validation errors in JSON
- update tag with JSON response
- delete tag with JSON response
- ledger authorization denial in JSON

- [ ] **Step 3: Run the tag web and API tests and confirm failure**

Run:

```bash
php artisan test --compact tests/Feature/Ledger/TagTest.php tests/Feature/Api/V1/Ledger/TagApiTest.php
```

- [ ] **Step 4: Implement tag input Data classes**

Move current `TagRequest` behavior into:

- `StoreTagData`
- `UpdateTagData`

Include:

- route model injection
- custom messages
- `authorize()` using the ledger policy through injected context

- [ ] **Step 5: Implement tag query and use-case actions**

Split into:

- query action(s) for listing/page data
- top-level actions for store, update, destroy

Keep shared-core results transport-agnostic.

- [ ] **Step 6: Implement tag output Data classes**

Create separate output classes for:

- API entity payloads
- web page payload including deferred `tags`

- [ ] **Step 7: Replace the legacy web controller path**

Refactor `app/Http/Controllers/Ledger/TagController.php` in place into a thin web transport adapter. Do not move the browser controller class path during the pilot, because the current Wayfinder imports already point at that controller.

- [ ] **Step 8: Add API routes and controller for tags**

Expose the same shared actions behind `/api/v1/...` tag endpoints.

- [ ] **Step 9: Update the tags page only as needed to consume the new output contract**

Keep it pure Inertia:

- no browser API calls
- deferred `tags` still render with `<Deferred>`
- Wayfinder stays in use for browser routes
- generated browser-side Wayfinder imports continue to target `App\Http\Controllers\Ledger\TagController`

- [ ] **Step 10: Run pilot tests and make them pass**

Run:

```bash
php artisan test --compact tests/Feature/Ledger/TagTest.php tests/Feature/Api/V1/Ledger/TagApiTest.php tests/Feature/Architecture/DomainExceptionTranslationTest.php
```

- [ ] **Step 11: Run linting/formatting for PHP and JS**

Run:

```bash
vendor/bin/pint --dirty --format agent
npm run lint
```

- [ ] **Step 12: Commit the pilot module migration**

```bash
git add app/Data/Tags app/Actions/Tags app/Http/Controllers/Ledger/TagController.php app/Http/Controllers/Api/V1/Ledger routes/ledger.php routes/api.php resources/js/pages/ledgers/tags/index.tsx tests/Feature/Ledger/TagTest.php tests/Feature/Api/V1/Ledger/TagApiTest.php
git commit -m "Migrate tags to shared-core web and API architecture"
```

**Dependency gate for next phase:** The pilot must prove both surfaces are stable and simpler than the legacy pattern. If not, refine the architecture before scaling.

---

### Phase 5: Extend Pattern To Adjacent Modules

**Outcome:** Reuse the pilot architecture on a small batch of adjacent modules before touching the heaviest workflows.

**Recommended order:**

1. Payees
2. Categories
3. Bills or Budgets, depending on which exposed fewer follow-up changes during earlier phases

**Files:**

- Create/Modify matching `app/Data/...`, `app/Actions/...`, `app/Http/Controllers/Web/...`, `app/Http/Controllers/Api/V1/...`
- Modify matching `routes/ledger.php`, `routes/api.php`
- Modify existing page components under `resources/js/pages/ledgers/...`
- Add or update feature tests under `tests/Feature/Ledger/...` and `tests/Feature/Api/V1/Ledger/...`

- [ ] **Step 1: Migrate Payees using the same structure as Tags**
- [ ] **Step 2: Run payee web/API tests and fix any shared-core gaps exposed by search/filter behavior**
- [ ] **Step 3: Migrate Categories using the same structure, but treat hierarchy, reassign-on-delete, and reorder as explicit complexity markers**
- [ ] **Step 4: Run category web/API tests and fix any shared-core gaps exposed by hierarchy, reorder, and reassignment behavior**
- [ ] **Step 5: Choose the next adjacent module based on what the pilot proved about premium gating and orchestration complexity**
- [ ] **Step 6: Migrate either Bills or Budgets next and treat premium gating and service-driven logic as explicit test targets**
- [ ] **Step 7: Run Pint, ESLint, and affected test suites**
- [ ] **Step 8: Commit after each module or after each stable slice, not as one giant commit**

**Dependency gate for next phase:** At least three adjacent modules should work cleanly with the architecture before moving to complex transaction/import flows.

---

### Phase 6: Migrate Complex Modules

**Outcome:** Apply the proven pattern to high-complexity modules that need multi-step orchestration and richer read models.

**Recommended order:**

1. Accounts
2. Whichever of Bills or Budgets remains after Phase 5
3. Transactions
4. Import
5. Reports

**Files:**

- Module-specific data/action/controller/test files under the established conventions
- Likely additions to `app/Exceptions/Domain/...`
- Additional query composer classes for page-level and endpoint-level reads

- [ ] **Step 1: Migrate Accounts using top-level query composers for deferred sections and top-level use-case actions for writes**
- [ ] **Step 2: Add focused tests for reorder, balance adjustment, and authorization edge cases**
- [ ] **Step 3: Migrate Bills, especially payment and premium-gated behavior**
- [ ] **Step 4: Add focused tests for recurring bill side effects and domain failures**
- [ ] **Step 5: Migrate Transactions with special care for filters, infinite scroll, attachments, bulk actions, and split/transfer workflows**
- [ ] **Step 6: Add focused tests for transaction query actions, deferred props, and shared business invariants**
- [ ] **Step 7: Migrate Import only after transaction write paths are stable**
- [ ] **Step 8: Migrate Reports last, using query actions and page output Data for heavy read composition**
- [ ] **Step 9: Run focused test suites after each module before moving on**
- [ ] **Step 10: Commit each complex module migration separately**

**Dependency gate for next phase:** Complex modules should be stable before optional tooling or cleanup expands the scope.

---

### Phase 7: Optional Contract Tooling And Cleanup

**Outcome:** Add contract generation and optional query-builder support once the architecture has proven itself in real modules. This phase is intentionally sequenced last for risk control, but unlike Phases 1-4 it is optional and not a prerequisite for architectural success.

**Files:**

- Modify: `composer.json`
- Create: `app/Providers/TypeScriptTransformerServiceProvider.php`
- Modify: `bootstrap/providers.php`
- Create/Modify: generated TS output destination as chosen by the provider config
- Optionally modify: `resources/js/types/index.ts`
- Optionally modify: module API query actions to use `spatie/laravel-query-builder`
- Create: `tests/Feature/Architecture/TypeContractGenerationTest.php` if you want a smoke check around generated types or commands

- [ ] **Step 1: Install `spatie/laravel-typescript-transformer`**

Run:

```bash
composer require spatie/laravel-typescript-transformer
php artisan typescript:install --no-interaction
```

- [ ] **Step 2: Configure the generated provider to load `LaravelDataTypeScriptTransformerExtension`**

Use the provider created by `typescript:install` and configure it to transform Data classes from your chosen app directories.

- [ ] **Step 3: Decide the generated TS output destination and wire it into frontend imports**

Keep this explicit so manual types and generated types do not fight each other.

- [ ] **Step 4: Run `php artisan typescript:transform` and verify the generated types are usable**

- [ ] **Step 5: Optionally install and adopt `spatie/laravel-query-builder` for API read endpoints that now have real filter/sort complexity**

Run:

```bash
composer require spatie/laravel-query-builder
php artisan vendor:publish --provider="Spatie\QueryBuilder\QueryBuilderServiceProvider" --tag="query-builder-config" --no-interaction
```

- [ ] **Step 6: Only replace manual read filtering with Query Builder where it reduces complexity, not by default**

- [ ] **Step 7: Run full verification on affected PHP and frontend surfaces**

Run:

```bash
npm run lint
php artisan test --compact
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 8: Commit the optional tooling adoption separately from module logic**

**Dependency gate for completion:** Generated contracts and optional query tooling must improve clarity, not create a second parallel type system.

---

## Verification Checklist Per Phase

Before advancing to the next phase:

- [ ] All new or changed feature tests for the phase pass
- [ ] `vendor/bin/pint --dirty --format agent` has been run if PHP changed
- [ ] `npm run lint` has been run if JS/TS changed
- [ ] The phase outcome matches its dependency gate
- [ ] One commit exists for the phase or stable slice

## Rollout Notes

- Prefer the smallest module that can prove the architecture before scaling.
- If a later module exposes a design flaw, pause and repair the shared pattern before migrating more modules.
- Keep Fortify unchanged until the business-module rollout is stable.
- Do not migrate admin browser pages to API fetching if the agreed browser rule is pure Inertia.
- If Data-first request handling fails for a specific request type, keep `FormRequest` as an explicit exception rather than forcing inconsistency.
