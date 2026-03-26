# Homepage Redesign - Screenshot-Driven Feature Showcase

**Date:** 2026-03-26
**Status:** Approved

## Overview

Redesign the Feenans landing page from a text-heavy, icon-card layout to a screenshot-driven feature showcase. Inspired by [Finory's](https://www.finory.app/) design language (feature screenshots, clean sections, visual hierarchy) but adapted to Feenans' monochromatic theme with full dark/light mode support.

## Goals

1. Show the product immediately — screenshots as marketing material, not just icon cards
2. Highlight the custom billing cycle start date as a key differentiator
3. Respect system dark/light mode using the existing `useAppearance` hook
4. Keep the privacy/security messaging, but condense it
5. Mobile-first, responsive

## Page Structure

8 sections, top to bottom:

| #   | Section            | Dark Mode BG        | Light Mode BG        | Purpose                                                 |
| --- | ------------------ | ------------------- | -------------------- | ------------------------------------------------------- |
| 1   | Sticky Header      | `background` (dark) | `background` (white) | Logo + auth nav                                         |
| 2   | Hero               | `background`        | `background`         | Tagline left + accounts screenshot right                |
| 3   | Trust Banner       | `muted/30` variant  | `muted/30` variant   | 4-item strip: no admin, data isolation, export, 2FA     |
| 4   | Features Gallery   | `muted` variant     | `muted` variant      | Horizontal scroll, 5 screenshot cards                   |
| 5   | Cycle Flexibility  | `background`        | `background`         | Dedicated callout: text left + cycle option cards right |
| 6   | Privacy & Security | `muted` variant     | `muted` variant      | Condensed 4-card trust grid                             |
| 7   | CTA                | `background`        | `background`         | Final call-to-action                                    |
| 8   | Footer             | `background`        | `background`         | Logo + copyright                                        |

The alternating `background` / `muted` pattern creates visual rhythm. Tailwind `dark:` variants handle all color switching automatically via the existing `.dark` class on `<html>`.

## Section Details

### 1. Sticky Header

Unchanged from current design: logo left, auth buttons right. `bg-background/80 backdrop-blur-sm` with `border-b`.

### 2. Hero Section

**Layout:** Two-column grid (`lg:grid-cols-2`), stacks vertically on mobile.

**Left column:**

- Badge pill: shield icon + "Privacy-first personal finance"
- Heading: `"Your finances. Your rules."` + muted `"Nobody watching."`
- Subtitle: concise value prop
- CTA buttons: "Get started free" (primary) + "See how it works" (outline)

**Right column:**

- `account.png` (dark) / `account-light.png` (light) screenshot
- Styled with rounded corners (`rounded-xl`), subtle border (`border border-border`), dramatic drop shadow
- No browser chrome — just the screenshot with a subtle color-tinted gradient behind it via a wrapper div

**Mobile:** Text stacks above screenshot. Screenshot spans full width.

### 3. Trust Banner

Carried over from current design but unchanged in structure — 4-column grid on desktop, 2-column on tablet, 1-column on mobile. Icons + title + short description.

### 4. Features Gallery

**Section header:** Centered — "FEATURES" label + "Everything you need, nothing you don't" heading + subtitle.

**Gallery:** Horizontal scrolling track with CSS scroll-snap (`scroll-snap-type: x mandatory`).

**5 cards, each 380px wide (flex-shrink: 0):**

| Card | Screenshot     | Title                  | Description                                                                                                      |
| ---- | -------------- | ---------------------- | ---------------------------------------------------------------------------------------------------------------- |
| 1    | `account.png`  | Multi-Account Tracking | Bank accounts, credit cards, e-wallets in one view. Real-time balances with statement cycle tracking.            |
| 2    | `report.png`   | Powerful Reports       | Monthly trends, category breakdowns, spending heatmaps, and income analysis. Export any report as PDF.           |
| 3    | `budget.png`   | Budget Tracking        | Set spending limits per category. Visual progress bars and threshold alerts when you're close to your limit.     |
| 4    | `bill.png`     | Recurring Bills        | Track every subscription and recurring payment. Auto-creates transactions on due date and flags missed payments. |
| 5    | `category.png` | Smart Categories       | Two-level category hierarchy with custom colors. Transaction counts per category for instant spending insight.   |

**Card structure:**

- Top: screenshot image inside a wrapper with `bg-gradient-to-br from-muted/50 via-background to-muted/50` (subtle tinted gradient), `rounded-t-xl`, padding around the image
- Image: `rounded-lg`, `shadow-2xl` (dramatic shadow), `border border-border`
- Bottom: `p-5` with `h3` title + `p` description on the card's `bg-card` body

**Card hover:** `hover:-translate-y-1 transition-transform`

**Screenshot logic (applied to all screenshot elements):**

```tsx
// Helper component or inline logic
const src =
    resolvedAppearance === 'light'
        ? `/screenshots/${name}-light.png`
        : `/screenshots/${name}.png`;

// With fallback: onError handler falls back to dark version
<img
    src={src}
    onError={(e) => {
        e.currentTarget.src = `/screenshots/${name}.png`;
    }}
/>;
```

### 5. Cycle Flexibility Callout

**Layout:** Two-column grid, stacks on mobile.

**Left column (text):**

- Label: "FLEXIBLE CYCLES"
- Heading: `"Your month starts"` + muted/emphasized `"when you say it does."`
- Paragraph: explain that most apps assume the 1st, but Feenans lets you set any start date — salary day, credit card billing cycle, etc. All budgets, reports, and summaries follow.

**Right column (visual):**

- 4 stacked cards showing different cycle options:
    1. "Standard Month" — 1 Mar – 31 Mar 2026
    2. "Salary Day (25th)" — 25 Feb – 24 Mar 2026
    3. "Credit Card Cycle (16th)" — 16 Feb – 15 Mar 2026 **(visually selected with active ring/border)**
    4. "Custom (any day)" — "Pick your own start date"
- Each card: `rounded-lg border border-border bg-card p-4`, flex row with label+dates left and a radio-style indicator right
- Active card: `border-primary bg-accent/50` with filled radio dot

### 6. Privacy & Security (Condensed)

**Merged from the current 2 full sections into 1.**

**Header:** Centered — "PRIVACY & SECURITY" label + "Your data stays yours. Period." heading + one-line subtitle.

**4-card grid** (`lg:grid-cols-4`, `sm:grid-cols-2`):

| Card | Icon        | Title                 | Description                                                                               |
| ---- | ----------- | --------------------- | ----------------------------------------------------------------------------------------- |
| 1    | ShieldCheck | No Admin Access       | No interface can browse, search, or export your ledgers or transactions.                  |
| 2    | Lock        | Data Isolation        | Every query scoped to your account. Your data never mingles with others.                  |
| 3    | Key         | Two-Factor Auth       | TOTP-based 2FA with backup recovery codes. Password confirmation for sensitive actions.   |
| 4    | Download    | Full Data Portability | Export everything as JSON or CSV anytime. Delete your account permanently with one click. |

Each card: icon in a `bg-primary/10 rounded-lg` container + title + short description. Same `FeatureCard`-like styling.

### 7. CTA Section

Carried over from current design — centered heading, subtitle, primary + outline buttons, "No credit card required" note.

### 8. Footer

Unchanged — logo left, copyright right.

## Theme Integration

### Dark/Light Mode

The welcome page currently builds its own layout (no shared app layout, no `initializeTheme()` call from the Inertia app). However, the FOUC-prevention script in `app.blade.php` already applies `.dark` to `<html>` before React hydrates. Since the welcome page renders inside the same Blade template, Tailwind `dark:` variants work out of the box.

**To reactively switch screenshots when theme changes**, import `useAppearance`:

```tsx
import { useAppearance } from '@/hooks/use-appearance';

// Inside component:
const { resolvedAppearance } = useAppearance();
```

This gives `'light'` or `'dark'` and re-renders when the system preference changes (the hook already listens to `matchMedia` changes).

### Screenshot Assets

**Convention:**

- Dark mode: `/screenshots/{name}.png` (existing files)
- Light mode: `/screenshots/{name}-light.png` (to be added by user)

**Fallback:** If the light variant doesn't exist, `onError` falls back to the dark version. This means light mode works even before the user provides `-light` screenshots.

**Helper component:**

```tsx
function ScreenshotImage({
    name,
    alt,
    className,
}: {
    name: string;
    alt: string;
    className?: string;
}) {
    const { resolvedAppearance } = useAppearance();
    const src =
        resolvedAppearance === 'light'
            ? `/screenshots/${name}-light.png`
            : `/screenshots/${name}.png`;

    return (
        <img
            src={src}
            alt={alt}
            className={className}
            onError={(e) => {
                if (e.currentTarget.src !== `/screenshots/${name}.png`) {
                    e.currentTarget.src = `/screenshots/${name}.png`;
                }
            }}
        />
    );
}
```

## Responsive Behavior

| Breakpoint          | Hero                                 | Gallery                        | Cycle                           | Trust Grid    |
| ------------------- | ------------------------------------ | ------------------------------ | ------------------------------- | ------------- |
| Mobile (<640px)     | Single column, text above screenshot | Full-width scroll, cards 300px | Single column, text above cards | Single column |
| Tablet (640-1024px) | Single column                        | Scroll, cards 340px            | Single column                   | 2-column grid |
| Desktop (1024px+)   | 2-column grid                        | Scroll, cards 380px            | 2-column grid                   | 4-column grid |

## What's Removed

- The 12-feature icon card grid (replaced by 5 screenshot cards in the gallery)
- Separate Privacy section (merged into condensed trust grid)
- Separate Security section (merged into condensed trust grid)
- The `coreFeatures`, `privacyItems`, `securityItems` data arrays

## What's Kept

- Header (unchanged)
- Trust banner strip (unchanged)
- CTA section (unchanged)
- Footer (unchanged)
- All existing imports and routing logic (`auth.user` conditional, `canRegister`, wayfinder routes)

## What's New

- `ScreenshotImage` helper component (or inline in welcome.tsx)
- Hero screenshot (accounts page)
- Horizontal scrolling feature gallery with 5 screenshot cards
- Dedicated cycle flexibility section with visual card stack
- Condensed 4-card privacy & security grid

## Files Changed

| File                             | Change                                                                  |
| -------------------------------- | ----------------------------------------------------------------------- |
| `resources/js/pages/welcome.tsx` | Full rewrite of page content, keep header/footer/routing logic          |
| `public/screenshots/*-light.png` | New light mode screenshots (added by user, fallback to dark if missing) |

No new dependencies. No backend changes. No new routes.
