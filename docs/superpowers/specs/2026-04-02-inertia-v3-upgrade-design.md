# Inertia v3 Upgrade Design

**Date:** 2026-04-02  
**Branch:** inertia-v3-upgrade  
**Status:** Draft

---

## Summary

Upgrade the application from Inertia v2 to v3 using the plugin-native `@inertiajs/vite` bootstrap and SSR flow.

- Upgrade the Laravel adapter to `inertiajs/inertia-laravel:^3.0`.
- Upgrade the React adapter to `@inertiajs/react:^3.0`.
- Add `@inertiajs/vite:^3.0` and make it the canonical Inertia bootstrap path.
- Preserve current user-facing behavior for SSR, deferred props, partial reloads, forms, shared props, and exception rendering.
- Do not adopt new v3 features unless they are required to maintain compatibility.

## Current State

- `composer.json` currently requires `inertiajs/inertia-laravel:^2.0` and the installed version is `2.0.22`.
- `package.json` currently requires `@inertiajs/react:^2.3.7`.
- The application already satisfies v3 runtime requirements with PHP 8.3, Laravel 12, React 19, and Node 23.
- Inertia boot is currently wired manually across `resources/js/app.tsx`, `resources/js/ssr.tsx`, `vite.config.ts`, `resources/views/app.blade.php`, and `config/inertia.php`.
- SSR is enabled in the current `config/inertia.php`.
- The root Blade view currently uses `@viteReactRefresh`, a page-specific `@vite([... , "resources/js/pages/{$page['component']}.tsx"])` call, `<title inertia>`, `@inertiaHead`, and `@inertia`.
- `HandleInertiaRequests` already shares a substantial set of props, including deferred and optional props such as `notifications` and `transactionModalData`.
- The frontend makes broad use of `Deferred` across ledger dashboard, accounts, reports, import, categories, budgets, bills, tags, payees, and activity pages.
- Existing backend feature tests already cover centralized Inertia exception translation and partial reload exception behavior.

## Breaking-Change Surface Found In This Repo

The highest-risk upgrade surface in this codebase is bootstrap and SSR, not page-level API usage.

### Found

- Manual Inertia bootstrap and SSR entry setup.
- Old `config/inertia.php` structure.
- Broad `Deferred` usage that must be checked against the v3 `Deferred` reloading behavior.
- Browser-only theme initialization via `initializeTheme()` at module scope in `resources/js/app.tsx`, which is a risk when the same entry file is reused for SSR.

### Not Found In App Code

- `router.on('invalid', ...)`
- `router.on('exception', ...)`
- `document.addEventListener('inertia:invalid', ...)`
- `document.addEventListener('inertia:exception', ...)`
- `router.cancel()`
- `hideProgress()` / `revealProgress()`
- `Inertia::lazy()` / `LazyProp`
- direct `qs` imports
- direct `axios` imports
- direct `lodash-es` imports

This means the migration should stay focused on the bootstrap/config/SSR surface instead of chasing breaking changes that are not present here.

## Goals

- Upgrade server and client adapters to Inertia v3.
- Adopt `@inertiajs/vite` as the primary and documented Inertia bootstrap path.
- Simplify the root Blade view so it matches the v3 root-template shape.
- Keep SSR working in development and production.
- Preserve current Inertia data flows, shared props, partial reloads, and exception rendering behavior.
- Keep the upgrade small and compatibility-focused.

## Non-Goals

- Refactoring the application to adopt new v3 features such as `useHttp`, optimistic updates, layout props, default layouts, `preserveErrors`, or new exception APIs.
- Rewriting backend exception translation.
- Reworking the application into an API-driven frontend.
- Refactoring page components that are already compatible.
- Changing the existing product architecture beyond what the upgrade requires.

## Architecture Decision

### Recommended Approach

Use a full plugin-native Inertia v3 upgrade.

- `resources/js/app.tsx` becomes the canonical browser entry point.
- `@inertiajs/vite` owns plugin-native page resolution and development SSR behavior.
- Production SSR continues to use a dedicated SSR bundle and `php artisan inertia:start-ssr` unless version-specific implementation work proves a different setup is both supported and necessary.
- `resources/views/app.blade.php` moves to the v3 root-template shape with a single app entry instead of page-specific asset injection.
- `config/inertia.php` is republished and then re-customized on top of the new structure.

This is the best long-term fit because it removes duplicated bootstrap logic and aligns the application with the v3 documentation instead of carrying forward the v2 bootstrap shape.

### Alternatives Considered

#### 1. Hybrid plugin plus manual SSR

Install `@inertiajs/vite` but keep a dedicated `resources/js/ssr.tsx` and manual SSR wiring for the upgrade.

- Pros: lower immediate SSR migration risk if browser-only startup code is hard to isolate.
- Cons: leaves more bootstrap duplication and does not fully realize the v3 simplification.

#### 2. Two-step transition

Upgrade versions now and postpone the Blade/bootstrap cleanup to a follow-up pass.

- Pros: smallest first diff.
- Cons: prolongs the time spent in a mixed v2/v3 shape and creates immediate follow-up work.

The plugin-native approach is preferred. The target is still a cleaner v3 bootstrap, but this design does not assume that production SSR should stop using a dedicated `resources/js/ssr.tsx` entry during the upgrade.

## Likely Files Touched

- `composer.json`
- `composer.lock`
- `package.json`
- `package-lock.json`
- `config/inertia.php`
- `vite.config.ts`
- `resources/js/app.tsx`
- `resources/js/ssr.tsx`
- `resources/views/app.blade.php`
- `resources/js/hooks/use-appearance.tsx`
- Any page or component that needs an explicit `Deferred` reloading treatment after verification
- Existing Inertia-focused feature tests if expectations or setup need adjustment

## Upgrade Scope

### 1. Dependency And Config Layer

- Upgrade `inertiajs/inertia-laravel` to `^3.0`.
- Upgrade `@inertiajs/react` to `^3.0`.
- Install `@inertiajs/vite`.
- Republish the Inertia config file.
- Re-apply this app's SSR and history configuration intentionally instead of guessing through a blind merge.
- Clear cached views after the config and Blade updates.

The new `config/inertia.php` structure should be reapplied with the actual v2-to-v3 key move this repo needs: page path and extension settings move from `testing.page_paths` and `testing.page_extensions` into top-level `pages.paths` and `pages.extensions`, while `testing.ensure_pages_exist` remains as the testing-specific flag.

### 2. Frontend Bootstrap Layer

- Refactor `resources/js/app.tsx` to the v3 plugin-native `createInertiaApp()` shape.
- Remove manual page resolution and manual bootstrap code that the plugin now handles.
- Preserve current global providers such as tooltip and privacy-mode wrappers.
- Ensure the browser entry file is SSR-safe where shared code paths require it and does not execute browser-only side effects at import time.
- Use the correct client hydration path for SSR-rendered HTML instead of unconditionally remounting the app on the client.

The main repo-specific issues here are `initializeTheme()` and client hydration. `initializeTheme()` currently runs at module scope, so it must move behind a browser-only boundary or equivalent SSR-safe guard. The browser entry also currently uses `createRoot(...)`, which must be replaced with the documented SSR-compatible hydration path when rendering pages that were server-rendered.

### 3. SSR And Vite Layer

- Add `inertia()` from `@inertiajs/vite` to `vite.config.ts`.
- Let the plugin handle development SSR when running `npm run dev`.
- Keep production SSR based on a dedicated SSR bundle plus `php artisan inertia:start-ssr`.
- Simplify redundant client-side bootstrap wiring, but do not remove the dedicated production SSR entry unless the final implementation proves that change is both supported and safe.

The upgrade should preserve current production behavior while simplifying the development flow.

### 4. Blade Root Template Layer

- Replace page-specific `@vite` asset loading with a single app entry.
- Optionally adopt the v3 Blade components `<x-inertia::head>` and `<x-inertia::app>` if their head/title fallback behavior is useful; keeping `@inertiaHead` and `@inertia` is also acceptable if that proves lower risk for this upgrade.
- Remove the old `<title inertia>` pattern in favor of the component-based root template.
- Keep the dark-mode no-flash handling only if it still serves a real purpose after the entry-point cleanup.

This is a structural upgrade, not a design refresh.

### 5. Page And Component Layer

Most page components should remain unchanged.

- Existing `router.reload({ only: [...] })` and `except: [...]` behavior should stay in place.
- Existing Wayfinder-generated route helpers should remain in use.
- Existing deferred props should continue to work.

The page and component layer still needs compatibility verification in two specific areas.

- `Deferred` reloading semantics changed in v3. Deferred content stays visible during partial reloads instead of returning to the fallback. If any current view depends on the fallback reappearing, that page should use the `reloading` render prop explicitly.
- Form submit-state timing also needs verification. This repo is form-heavy across auth, onboarding, settings, security, and ledger create/edit flows, and v3 changes when `processing` and `progress` reset relative to `onFinish`. Existing loading and disabled-state behavior must be re-tested instead of assumed.

## Data Flow

Initial page visits remain Laravel-driven.

- Laravel renders the root Blade template.
- Inertia injects the page object and shared props.
- The client hydrates from `resources/js/app.tsx`.

The key change is not the overall request model; it is the bootstrap authority.

- In v2, this repository manually wires page resolution and SSR setup.
- In v3, `@inertiajs/vite` becomes the primary browser bootstrap mechanism while production SSR still uses the explicit SSR bundle/service flow unless implementation evidence supports a deeper consolidation.

### Development

- SSR should work via the normal Vite dev server.
- There should no longer be a separate dev-only SSR daemon requirement.

### Production

- Production still builds the assets and starts the SSR service explicitly.
- The operational flow remains familiar even after the bootstrap cleanup.

### Shared Props And Partial Reloads

- `HandleInertiaRequests` remains the source of truth for shared props.
- Existing partial reload patterns such as `router.reload({ only: [...] })` continue unchanged.
- Existing `optional()` and `defer()` semantics remain the server-side pattern for expensive props.

## Error Handling

Keep the current backend exception behavior intact.

- The repo already has strong coverage around centralized Inertia exception rendering.
- The upgrade should preserve that behavior instead of introducing v3's newer exception APIs during the same change.
- Frontend event rename work is not part of the initial scope because the relevant old event listeners were not found in app code.

If SSR failures become hard to notice during the migration, the test environment may temporarily enable strict SSR failure behavior so silent client-side fallback does not hide real regressions.

## Verification Strategy

Verification should happen in layers so failures are easy to localize.

### 1. Baseline Verification

- Establish a working pre-upgrade baseline in the isolated worktree.
- For this repo, Blade-rendering Inertia tests require built frontend assets because the root Blade template resolves Vite output.

### 2. Backend Verification

- Re-run the existing Inertia architecture and exception translation tests.
- Re-run targeted page tests that exercise deferred props and partial reloads.
- Prefer narrow feature test files or filters over the entire suite when verifying each upgrade step.

### 3. Frontend Verification

- Run `npm run lint`.
- Run `npm run types:check`.
- Run `npm run build`.
- Run the repo's SSR build path explicitly with `npm run build:ssr` unless the package scripts are intentionally changed during the upgrade.

The production builds are important because they validate the updated plugin/bootstrap shape and catch page-resolution or manifest issues in both the browser and SSR bundles.

### 4. SSR Verification

- In development, run the normal Vite dev server only and verify a known SSR route renders without starting `php artisan inertia:start-ssr`.
- Confirm the application still renders with SSR enabled.
- Start the SSR service with `php artisan inertia:start-ssr` after producing the SSR bundle.
- Verify the SSR service is healthy with `php artisan inertia:check-ssr`.
- Verify at least one SSR-rendered route loads successfully through the upgraded stack.
- Verify the browser hydrates the SSR-rendered route without remounting the app or producing hydration warnings.
- Check for hydration mismatches, browser-only SSR crashes, or page-resolution failures.
- Review browser logs after the upgrade to surface silent runtime problems.

### 5. Manual Smoke Flows

- Login and onboarding flow.
- Dashboard and notifications.
- Deferred-heavy ledger pages such as dashboard, accounts, reports, and import.
- Form submission flows and partial reload behavior.

## Risks And Mitigations

### SSR-Safety Regressions

Risk:
`app.tsx` may execute browser-only logic while rendering on the server.

Mitigation:
Move module-scope browser side effects behind SSR-safe guards or client-only execution.

### Root Template Drift

Risk:
The current Blade shell includes legacy assumptions such as page-specific asset injection and `<title inertia>`.

Mitigation:
Replace the root template as one coherent change and verify it with existing feature tests plus a production build.

### Deferred UX Drift

Risk:
Some views may implicitly rely on the v2 behavior where deferred content re-enters the fallback during reloads.

Mitigation:
Audit the existing deferred-heavy pages after the adapter upgrade and add explicit `reloading` UI only where the old behavior mattered.

### Config Drift After Republishing

Risk:
Republishing `config/inertia.php` can drop app-specific settings if reapplied casually.

Mitigation:
Compare the new published file with the existing app settings and reapply only the intentional customizations.

## Success Criteria

- The app runs on `inertiajs/inertia-laravel:^3.0` and `@inertiajs/react:^3.0`.
- `@inertiajs/vite` is installed and used as the primary Inertia bootstrap path.
- `resources/views/app.blade.php` matches the v3 root-template strategy with a single app entry.
- Development SSR works through the normal Vite dev flow.
- Browser and SSR build output are valid, and the app still supports production SSR startup and health checks.
- SSR-rendered routes hydrate cleanly on the client without hydration warnings or full remount behavior.
- Existing targeted Inertia architecture, exception, deferred, and partial reload tests pass.
- No new hydration, SSR, or page-resolution errors appear in browser logs for critical flows.
- No user-visible regressions are introduced in forms, deferred sections, partial reloads, or exception pages.

## Handoff To Planning

The implementation plan should treat this as a compatibility upgrade with a bootstrap cleanup, not as a product refactor.

The first tasks in the implementation plan should focus on:

- dependency upgrades
- config republish and root template cleanup
- plugin-native bootstrap refactor
- SSR-safe theme initialization
- targeted verification before any optional page-level adjustments
