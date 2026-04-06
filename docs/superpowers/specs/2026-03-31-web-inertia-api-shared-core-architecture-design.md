# Web Inertia + API Shared Core Architecture

**Date:** 2026-03-31  
**Branch:** enhancement  
**Status:** Draft

---

## Summary

Adopt a strict dual-surface architecture:

- **Web** stays fully Inertia-driven with no frontend API fetching.
- **API** becomes a separate Sanctum token surface for first-party mobile, desktop, and CLI clients.
- **Shared core logic** lives in transport-agnostic query actions, use-case actions, and domain exceptions.
- **Transport shaping** lives in input data classes, output data classes, controllers, and exception translators.
- **Request validation and request authorization** move from Laravel Form Requests to `spatie/laravel-data` input data classes.
- **Fortify remains unchanged for now** and is the only explicit architectural exception.

This keeps the web UX optimized for Inertia while allowing a durable first-party API without duplicating business logic.

## Shared Core Boundary

The shared core must stay transport-agnostic.

- Shared query actions return transport-agnostic read results or domain read models.
- Shared use-case actions return transport-agnostic write results or domain result objects.
- Shared core code does not know about Inertia deferred props, JSON envelopes, redirects, flash messages, or API resource wrappers.

Transport concerns live at the edge:

- **Input data** maps and authorizes incoming HTTP requests.
- **Output data** presents shared-core results to web or API consumers.
- **Controllers** connect transport to the application layer.
- **Exception translators** map failures into Inertia or JSON behavior.

Inertia lazy, optional, mergeable, and deferred props belong only in web output data classes, not in the shared query or use-case layer.

## Current State

- The application is primarily web-route driven, with most ledger features defined in `routes/ledger.php`.
- Product pages already use Inertia v2 patterns such as deferred props and partial reloads.
- Wayfinder is installed and already used across much of the frontend.
- API Resources already exist for many ledger entities, but they are mostly used to shape Inertia props and a few JSON responses.
- Admin is the clearest example of a page shell plus JSON API split, but the rest of the product is still mostly Inertia-first.
- Fortify currently handles authentication flows.
- The repository currently has a project rule stating new frontend features must fetch from backend APIs. This proposed architecture conflicts with that rule and would require that rule to be replaced or narrowed.

## Goals

- Keep the web product fully Inertia-native.
- Create a first-party API that shares the same business logic as the web surface.
- Make controllers transport adapters only.
- Standardize request contracts, validation, and request authorization on `spatie/laravel-data`.
- Separate request input contracts from response/output contracts.
- Prevent business logic duplication between web and API.
- Prevent controllers from becoming orchestration or read-composition layers.

## Non-Goals

- Reworking Fortify authentication flows right now.
- Converting the web product into an API-driven frontend.
- Making the API public for third-party developers in v1.
- Replacing every existing controller in one pass.

## Architecture Decision

### Recommended Approach

Use a **strict dual-surface shared-core** model.

- **Web controllers** return Inertia responses and never fetch or proxy `/api/*` routes from the browser.
- **API controllers** return JSON and are authenticated with Sanctum tokens for first-party non-browser clients.
- Both controller surfaces call the same application layer.

### Alternatives Considered

#### 1. Full Data-first everywhere immediately

Move every web, API, and auth-adjacent flow into the new pattern at once.

- Pros: maximum consistency
- Cons: high migration cost, more risk, Fortify friction immediately

#### 2. Pragmatic hybrid rollout

Apply the pattern only to selected modules while letting simpler controllers remain more traditional.

- Pros: lowest short-term cost
- Cons: architectural drift is likely and controller fat can return quickly

The strict dual-surface shared-core approach is preferred because it matches the desired long-term shape without forcing an auth rewrite up front.

## Core Rules

### 1. Web Surface

- Web routes remain the source of truth for browser pages.
- The web frontend does not call `/api/*` routes.
- Inertia remains the browser transport for reads and writes.
- Use Inertia features such as `defer`, partial reloads, mergeable props, and standard form/visit flows.
- Search, filters, pagination, infinite scroll, and modal reloads stay on web routes using Inertia semantics.

### 2. API Surface

- API routes serve first-party mobile, desktop, and CLI clients.
- API auth uses Sanctum token authentication, not SPA cookie assumptions.
- API is versioned from the start, e.g. `App\Http\Controllers\Api\V1\...`.
- API is not public in v1, so contracts should be stable but do not need third-party governance yet.

### 3. Controllers

- Controllers are transport adapters only.
- Controllers do not contain business logic.
- Controllers do not orchestrate multiple use cases.
- Controllers should normally invoke exactly one top-level query action or one top-level use-case action.
- Controllers inject `Data` classes directly instead of `FormRequest` classes.
- Controllers may map shared-core results into one transport-specific output data object before returning the response.

### 4. Request Input

Use `spatie/laravel-data` input data classes as the default request contract.

Each input data class owns:

- request mapping
- transport validation
- request authorization via `authorize()`

Input data classes are resolved directly by controller injection using Laravel Data's request resolution.

Input data classes may inject:

- route parameters and route models
- current user context
- other request-derived values needed for authorization or validation

Form Requests are no longer the default pattern. Fortify remains the temporary exception.

### Request Pipeline Requirements

Before the first module migration, the application must prove that Data-based request handling preserves the Laravel behaviors the web product depends on.

Phase 1 must establish one shared request-data convention that covers:

- route model injection
- authenticated user injection
- validation exception behavior for Inertia requests
- JSON `422` behavior for API requests
- validation messages and attributes when custom text is needed
- error bag conventions for web forms
- request normalization or preparation before validation
- file upload handling

No module migration should proceed until a spike confirms those behaviors work with injected `Data` classes in this codebase.

If the spike shows that specific request types cannot preserve required Laravel or Inertia behavior cleanly, those cases should continue using `FormRequest` as an explicit exception instead of forcing a fragile partial replacement.

### 5. Request Authorization

- Request authorization lives in the data class `authorize()` method.
- Policy checks may still be used there.
- Controllers should not call `authorize()` directly except in legacy or Fortify exception areas.
- Request authorization answers only whether the request may proceed.
- Business or state-transition rules do not belong in request authorization.
- Authorization must use a consistent injection pattern for route models and current-user context so policy checks do not drift between classes.

### 6. Read Side

All reads use query actions.

- Every page gets a page-level query action.
- Heavy sections should be composed from smaller section query actions.
- Controllers do not build page data inline.
- Shared query actions return transport-agnostic read results.

For web pages:

- page-level web output data may contain immediate, lazy, and deferred props
- web page assembly may compose one or more shared query actions and then map their results into web output data
- Spatie Data's Inertia support should be used where it makes the page contract clearer
- deferred groups should be intentionally grouped to avoid too many follow-up requests

For API endpoints:

- API controllers may compose the same shared query actions
- API output data maps the shared read results into JSON payloads without inheriting Inertia concerns

For larger screens or multi-section endpoints, composition should live in an explicit page query or endpoint query class rather than in controllers. Controllers may invoke one top-level query composer, but they should not manually stitch together multiple query results inline.

This is required to prevent deferred page controllers from turning into read-composition layers.

### 7. Write Side

All writes use top-level use-case actions.

- One endpoint maps to one top-level use-case action.
- That action may compose smaller actions internally.
- Controllers do not orchestrate workflows.
- Use-case actions enforce domain and business invariants.
- Use-case actions own transaction boundaries when multiple writes are involved.
- Side effects such as events, notifications, activity logs, recalculations, and file coordination belong in the use-case layer.

### 8. Domain Failures

Use typed domain exceptions for business failures.

- Request validation failures remain transport-level validation failures.
- Request authorization failures remain authorization failures.
- Business failures are thrown as typed domain exceptions by query or use-case actions.
- Controllers do not translate these failures inline.

Typed domain exceptions are shared-core outputs. Their transport translation happens only at the edge.

Domain exceptions should carry:

- a stable internal code
- a user-safe message
- optional structured context for logging or API responses

### 9. Output Contracts

Use separate output data classes.

- Input data classes and output data classes are never the same class.
- Query and use-case actions return domain results or result objects.
- Output data classes shape the transport response for web and API.

This keeps request concerns, domain concerns, and presentation concerns separate.

## Response Translation

Shared actions must be translated differently at the edge.

### Web / Inertia

- validation errors use normal Laravel/Inertia error bag behavior
- authorization failures use normal authorization behavior
- domain exceptions on mutation requests are translated into redirects with flash or user-visible error messaging
- domain exceptions on GET-like Inertia requests are translated into HTTP error responses rendered through the shared Inertia exception pipeline, not redirects
- deferred prop follow-ups and partial reloads stay on Inertia semantics and must not return ad hoc plain JSON error envelopes
- unexpected exceptions use normal reporting and failure behavior

### API / JSON

- validation errors return JSON `422`
- authorization failures return JSON `401` or `403`
- domain exceptions return structured JSON `4xx` responses
- unexpected exceptions return JSON `500` with standard reporting

This translation should be centralized in Laravel exception handling, not repeated in every controller.

### Inertia Request-Type Matrix

The web surface needs explicit handling by request type:

- **initial page GET**: domain exceptions become HTTP error responses rendered as the application's standard Inertia error experience
- **partial reload GET**: domain exceptions remain GET-like failures and use the same centralized Inertia error pipeline, not redirects
- **deferred prop follow-up**: domain exceptions remain GET-like failures and use the same centralized Inertia error pipeline, not redirects
- **Inertia mutation request**: recoverable domain exceptions redirect back or to a safe route with flash or user-facing messaging

Only mutation requests use redirect-based recovery by default.

## Package Decisions

### Required

- `spatie/laravel-data`

### Strongly Recommended

- `spatie/laravel-typescript-transformer`
    - Generate TypeScript types from output data contracts for the web surface.
- `spatie/laravel-query-builder`
    - Useful for API filters, sorting, and read-side consistency once API endpoints grow.

### Keep And Use More Consistently

- `laravel/wayfinder`
    - Continue using it for web and browser-facing route helpers.
    - Do not treat Wayfinder as a dependency for non-browser first-party API clients.
    - API routes may use Wayfinder internally in this repository where helpful for tooling or tests, but the API contract itself must not depend on Wayfinder.

### Optional Later

- `laravel/precognition`
    - Promising, but not part of the v1 core architecture because the most established documentation path is still more Form Request-centric than Data-first.

## Folder And Naming Conventions

Recommended default structure:

- `app/Data/.../Input/...`
- `app/Data/.../Output/...`
- `app/Actions/.../Queries/...`
- `app/Actions/.../UseCases/...`
- `app/Exceptions/Domain/...`
- `app/Http/Controllers/Web/...`
- `app/Http/Controllers/Api/V1/...`

Recommended naming:

- `StoreTransactionData`
- `TransactionPageData`
- `StoreTransactionAction`
- `GetTransactionPageQuery`
- `TransactionNotEditable`

The goal is to avoid action sprawl and to make file intent obvious from the class name alone.

## Fortify Exception

- Do not change Fortify architecture yet.
- Fortify remains the temporary exception to the new request contract rule.
- The new architecture applies first to business modules.
- Browser and session-based authentication flows remain Fortify-backed or otherwise unchanged where they already exist.
- New non-browser API token flows live outside Fortify.
- Business modules must depend only on authenticated user context and policies, not on Fortify-specific response contracts.
- Auth can be redesigned later once the module architecture is stable.

## API Authentication Contract

The first API version is for first-party mobile, desktop, and CLI clients only.

For this architecture, "desktop" means non-browser desktop clients, not browser shells that behave like the existing web app.

Default v1 token rules:

- API clients authenticate with Sanctum personal access tokens.
- Token issuance and revocation live outside Fortify.
- Tokens are created per device or installation and require a human-readable device name.
- Tokens use a coarse first-party ability model in v1 and rely on normal policies and domain authorization for actual access control.
- Tokens are revocable individually.
- Rotation is handled by issuing a replacement token and revoking the prior token.
- Automatic token expiry is not required in v1; if that changes later, it must be treated as an API contract change.

This keeps the browser/session auth story separate from the non-browser token auth story.

## Impact On Existing Code

### Areas That Already Fit Well

- Inertia deferred prop testing is already established.
- Wayfinder is already present and configured.
- The app already has a useful split between page entry controllers and reusable domain logic in some modules.
- Existing API resources show that output shaping is already a recognized concern.

### Areas That Will Need Adjustment

- Admin pages currently use direct frontend `fetch()` calls to `/api/admin/*`; this conflicts with the pure-Inertia web rule and would eventually need to be migrated back to web-route/Inertia patterns if admin follows the same architecture.
- Existing controllers that directly build page data will need extraction into query actions.
- Existing write controllers that mix persistence and orchestration will need extraction into top-level use-case actions.
- Existing repository rule requiring new frontend features to fetch backend APIs must be revised if this architecture is adopted.

## Migration Strategy

Use a staged rollout.

### Phase 0

- update project rules so browser-facing product features use web/Inertia routes by default and non-browser consumers use API routes
- decide whether admin adopts the same pure-Inertia browser rule now or remains a temporary exception

### Phase 1

- install required packages
- establish folder and naming conventions
- define base exception translation strategy
- define base input and output data conventions
- prove the Data-based request pipeline preserves the Laravel/Inertia behavior the app depends on
- define the initial Sanctum token issuance and revocation endpoints before shipping API modules

### Phase 2

- migrate one representative ledger module end to end
- include both web and API surfaces
- prove query actions, use-case actions, input data, output data, and exception translation work together

### Phase 3

- migrate additional ledger modules incrementally
- keep Fortify untouched
- move admin web pages away from direct API fetching if the pure-Inertia web rule will apply there too

Suggested first migration candidates:

- payees
- tags
- budgets

These modules are smaller than transactions and imports, but still exercise the key patterns.

## Testing Strategy

- Test web and API separately at the transport layer.
- Shared actions should mainly be proven through real feature tests, not only isolated unit tests.
- Web tests should continue asserting:
    - component name
    - immediate props
    - deferred props
    - partial reload behavior
    - redirects, flashes, and error bags after writes
- API tests should assert:
    - Sanctum token auth
    - JSON shape
    - status codes
    - pagination, filters, and sort contracts
    - domain exception translation
- For shared use cases, cover at least one web feature path and one API feature path.
- If TypeScript generation is added, generated types become part of contract verification for the web surface.

## Risks

### 1. Authorization Ergonomics

`Data::authorize()` is workable, but it can become opaque if different classes reach into request globals or route models inconsistently.

Mitigation:

- establish one standard pattern for route model injection and user context injection
- keep authorization logic simple and transport-scoped

### 2. Action Explosion

Single-responsibility actions can create too many classes if boundaries are not disciplined.

Mitigation:

- enforce one top-level action per endpoint
- only extract smaller actions where reuse or complexity is real

### 3. Read-Side Drift

If reads are treated as “simple enough for controllers,” deferred pages will accumulate composition logic in controllers again.

Mitigation:

- require query actions for page reads and deferred sections from the start

### 4. Exception Translation Drift

If web and API handle domain failures ad hoc in controllers, the shared-core goal breaks down.

Mitigation:

- centralize domain exception translation

### 5. Project Rule Misalignment

The current frontend architecture rule conflicts with the proposed web approach.

Mitigation:

- update the repository rule set before or alongside implementation

## Open Decisions For Later

- whether admin should fully adopt the pure-Inertia web rule or remain a partial exception for longer
- whether Precognition should be introduced once the Data-first request pattern is stable
- whether a shared base data class should standardize authorization helpers, route access, and current-user access

## Final Decision

Proceed with a strict dual-surface architecture:

- **Web:** fully Inertia, no frontend API calls
- **API:** separate Sanctum token surface for first-party clients
- **Input:** `spatie/laravel-data` input data classes with `authorize()`
- **Reads:** query actions plus output data
- **Writes:** top-level use-case actions plus domain exceptions
- **Output:** separate output data classes
- **Auth:** Fortify unchanged for now as an explicit exception

This gives the application a consistent internal architecture without sacrificing the strengths of either Inertia or a dedicated first-party API.
