# Feenans

**Your finances. Your rules. Nobody watching.**

A self-hosted personal finance tracker built for people who refuse to hand their financial data to corporations. Track spending across multiple accounts, set budgets, monitor recurring bills, and generate rich reports — all running on your own infrastructure where no one else can touch your data.

## Why Feenans?

Most finance apps make money from your data. Feenans doesn't.

- **Self-hosted** — Your data lives on your server. Not ours, not a cloud provider's, yours.
- **No admin access** — Even the server operator cannot browse your transactions, budgets, or ledgers. There is no admin interface for user financial data.
- **Privacy mode** — One toggle masks all amounts in the UI. Use your finance app on the train, in a meeting, anywhere.
- **Full data portability** — Export everything as JSON or CSV at any time. Delete your account permanently with one click.
- **Zero tracking** — No analytics, no ads, no data mining. Just a finance app that does its job.
- **Modern stack** — Laravel 12, React 19, TypeScript, Tailwind CSS 4. Familiar tools, no lock-in.

## Features

### Core

- **Multi-Account Tracking** — Checking, savings, credit cards, e-wallets, cash — real-time balances, net worth overview, color-coded types, and statement cycle tracking
- **Smart Transactions** — Income, expenses, and transfers with splits, receipt attachments, bulk operations, and infinite scroll
- **Hierarchical Categories** — Two-level categories with custom colors, drag-and-drop reordering, and transaction counts
- **Tags & Payees** — Color-coded tags and payee cards with transaction drill-down and merge support
- **CSV Bank Import** — Upload any bank CSV, map columns with a guided wizard, and save mappings for repeat imports

### Planning

- **Budget Tracking** — Category-based spending limits with visual progress bars, rollover support, threshold alerts, and cycle-aware periods
- **Recurring Bills** — Daily, weekly, monthly, yearly, or custom schedules — auto-generates transactions on due dates and flags missed payments
- **Multi-Workspace** — Separate ledgers for personal, family, or project finances — each fully isolated
- **Flexible Billing Cycles** — Set any day as your cycle start. Match your salary day, credit card billing cycle, or whatever works for you

### Analytics

- **Income & Expense Reports** — Monthly trends, category breakdowns, spending heatmaps, payee analysis, period comparisons
- **Financial Health** — Net worth tracking, debt-to-asset ratio, savings rate charts
- **Cash Flow** — Daily income/expense flow with upcoming recurring bills calendar
- **Budget Performance** — Visual progress cards with status indicators across all budgets
- **PDF Export** — Export any report view as a clean PDF

### Security

- **Two-Factor Authentication** — TOTP-based 2FA with backup recovery codes
- **Activity Audit Trail** — Full change history with before/after diffs, filterable by type and action
- **Data Isolation** — Every database query scoped to the authenticated user
- **Password Confirmation** — Required for sensitive actions

### Extras

- **Onboarding Wizard** — Three-step guided setup: create workspace, add first account, done
- **Privacy Mode** — Toggle to mask all amounts across the entire UI
- **Dark Mode** — Full dark / light / system theme support
- **Mobile-First** — Responsive design built for phones first, enhanced for desktop
- **SSR Support** — Server-side rendering via Inertia.js for fast initial page loads
- **Password Reset While Logged In** — Send a reset link from security settings without logging out
- **Timezone Support** — Per-user timezone with `APP_TIMEZONE` env default

## Tech Stack

**Backend:** Laravel 12 · PHP 8.3 · Inertia.js 2 · Fortify · Sanctum · Wayfinder · DomPDF
**Frontend:** React 19 · TypeScript · Tailwind CSS 4 · Radix UI · Recharts · Vite 7
**Testing:** Pest 4 · ESLint 9 · Prettier 3 · Laravel Pint · React Compiler
**Infrastructure:** Docker · Redis · SQLite (default) / MySQL / PostgreSQL

## Getting Started

### Quick Setup

```bash
git clone <repository-url> feenans
cd feenans
composer setup
```

This runs the full setup: installs dependencies, copies `.env`, generates an app key, runs migrations, and builds frontend assets.

### Manual Setup

```bash
# Install PHP dependencies
composer install

# Copy environment file and generate app key
cp .env.example .env
php artisan key:generate

# Create SQLite database and run migrations
touch database/database.sqlite
php artisan migrate

# Install frontend dependencies and build
npm install
npm run build
```

### Start Development Server

```bash
composer dev
```

This starts four concurrent processes:

- PHP development server
- Queue worker
- Log viewer (Laravel Pail)
- Vite dev server (HMR)

The app will be available at `http://localhost:8000`.

### SSR Mode

```bash
composer dev:ssr
```

Builds SSR assets first, then starts the server with Inertia SSR instead of Vite HMR.

## Docker

### Build and Run

```bash
docker compose up -d
```

The application will be available at `http://localhost:8080` (configurable via `APP_PORT` in `.env`).

### Services

| Service     | Description                        |
| ----------- | ---------------------------------- |
| `app`       | Main application (PHP-FPM + Nginx) |
| `scheduler` | Laravel task scheduling            |
| `queue`     | Background job processing          |
| `redis`     | Cache and session store            |

### Persistent Volumes

- `app-storage` — File uploads and generated files
- `db-data` — SQLite database
- `redis-data` — Cache persistence

## Testing

```bash
# Run all tests
php artisan test --compact

# Run a specific test file
php artisan test --compact tests/Feature/ExampleTest.php

# Run tests matching a filter
php artisan test --compact --filter=testName

# Full CI check (lint + format + types + tests)
composer ci:check
```

Tests run against an in-memory SQLite database with isolated configuration.

## Code Quality

```bash
# PHP formatting (auto-fix)
composer lint

# PHP format check
composer lint:check

# JavaScript/TypeScript linting
npm run lint

# Format check
npm run format:check

# TypeScript type check
npm run types:check
```

## Project Structure

```
app/
├── Actions/          # Single-purpose action classes
├── Concerns/         # Shared traits
├── Enums/            # PHP enums
├── Http/
│   ├── Controllers/
│   │   ├── Ledger/   # Ledger feature controllers
│   │   └── Settings/ # User settings controllers
│   ├── Middleware/    # HTTP middleware
│   └── Requests/     # Form request validation
├── Models/           # Eloquent models
├── Notifications/    # Notification classes
├── Observers/        # Model observers (audit logging)
├── Policies/         # Authorization policies
├── Providers/        # Service providers
└── Services/         # Business logic services

database/
├── factories/        # Model factories for testing
├── migrations/       # Database migrations
└── seeders/          # Database seeders

resources/
├── css/              # Tailwind CSS with theme tokens
├── js/
│   ├── components/   # Reusable React components
│   ├── hooks/        # Custom React hooks
│   ├── layouts/      # Page layout components
│   ├── lib/          # Utility functions
│   ├── pages/        # Inertia page components
│   └── types/        # TypeScript type definitions
└── views/
    └── errors/       # Themed HTTP error pages

routes/
├── web.php           # Web routes
├── api.php           # Reserved API route file
├── ledger.php        # Ledger feature routes
├── settings.php      # Settings routes
└── console.php       # Artisan commands
```

## Environment Configuration

Key environment variables (see `.env.example` for full list):

| Variable                 | Default          | Description                  |
| ------------------------ | ---------------- | ---------------------------- |
| `APP_NAME`               | Laravel          | Application name             |
| `APP_URL`                | http://localhost | Base URL                     |
| `DB_CONNECTION`          | sqlite           | Database driver              |
| `QUEUE_CONNECTION`       | database         | Queue backend                |
| `CACHE_STORE`            | database         | Cache backend                |
| `MAIL_MAILER`            | log              | Mail driver                  |
| `APP_TIMEZONE`           | UTC              | Default application timezone |
| `LEDGER_FILESYSTEM_DISK` | local            | Storage for ledger exports   |

For production with Redis, update:

```env
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

## License

MIT
