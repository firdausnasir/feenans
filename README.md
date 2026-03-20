# Feenans

A private, self-hosted personal finance tracker. Manage multiple ledgers, track spending, budget wisely, and stay on top of bills — all without anyone watching your data.

## Key Features

- **Multi-Ledger Support** — Separate financial workspaces (personal, household, projects)
- **Multi-Account Tracking** — Checking, savings, credit cards, cash accounts with custom types
- **Smart Transactions** — Income, expenses, transfers with splits, attachments, and bulk operations
- **Recurring Bills** — Flexible schedules (daily, weekly, monthly, custom) with calendar day picker and human-readable frequency preview
- **Budget Tracking** — Category-based budgets with rollovers, alerts, cycle-aware auto-apply, and period date display
- **Hierarchical Categories** — Nested categories with drag-and-drop reordering
- **Tags** — Color-coded tags for cross-cutting organization
- **Payee Management** — Cards view with transaction drill-down, inline creation from transaction forms, merge support
- **Multi-View Reports** — Income & Expense (with spending heatmap), Financial Health (net worth, savings rate), Budget Performance, Cash Flow (daily flow + recurring calendar), all with PDF export
- **Activity Audit Trail** — Full change history with before/after diffs
- **CSV Import** — Bank statement import with guided steps, reusable column mappings
- **Full Data Export** — Export entire ledgers to JSON or transactions to CSV
- **Two-Factor Authentication** — TOTP-based 2FA with recovery codes
- **Password Reset While Logged In** — Send reset link from security settings
- **Timezone Support** — Configurable per-user timezone with `APP_TIMEZONE` env default
- **Color-Coded Accounts** — Custom colors for account identification
- **No Admin Panel** — Zero backdoor access to user financial data

## Tech Stack

### Backend

| Technology        | Version | Purpose                                       |
| ----------------- | ------- | --------------------------------------------- |
| PHP               | ^8.2    | Runtime                                       |
| Laravel           | 12      | Application framework                         |
| Inertia.js        | 2       | Server-side adapter (SPA bridge)              |
| Laravel Fortify   | 1       | Headless authentication                       |
| Laravel Sanctum   | 4       | Authentication foundation for Laravel features |
| Laravel Wayfinder | 0.1     | TypeScript route generation                   |
| DomPDF            | 3.1     | PDF report generation                         |
| SQLite            | —       | Default database (MySQL/PostgreSQL supported) |

### Frontend

| Technology       | Version | Purpose                        |
| ---------------- | ------- | ------------------------------ |
| React            | 19      | UI framework                   |
| TypeScript       | 5.7     | Type safety                    |
| Inertia.js React | 2       | Client-side adapter            |
| Tailwind CSS     | 4       | Utility-first styling          |
| Radix UI         | —       | Accessible headless components |
| Recharts         | 2.15    | Data visualization             |
| Lucide React     | —       | Icon library                   |
| Vite             | 7       | Build tool and dev server      |

### Development & Testing

| Tool           | Purpose                       |
| -------------- | ----------------------------- |
| Pest 4         | PHP testing framework         |
| Laravel Pint   | PHP code formatter            |
| ESLint 9       | JavaScript/TypeScript linting |
| Prettier 3     | Code formatting               |
| React Compiler | Automatic memoization         |

### Infrastructure

| Tool           | Purpose                            |
| -------------- | ---------------------------------- |
| Docker         | Multi-stage containerized builds   |
| Docker Compose | Local and production orchestration |
| Redis          | Caching and session store          |
| GitHub Actions | CI/CD (tests + linting)            |

## Prerequisites

- PHP 8.2+
- Composer 2
- Node.js 22+
- npm 10+
- SQLite (default) or MySQL/PostgreSQL

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

| Variable                 | Default          | Description                |
| ------------------------ | ---------------- | -------------------------- |
| `APP_NAME`               | Laravel          | Application name           |
| `APP_URL`                | http://localhost | Base URL                   |
| `DB_CONNECTION`          | sqlite           | Database driver            |
| `QUEUE_CONNECTION`       | database         | Queue backend              |
| `CACHE_STORE`            | database         | Cache backend              |
| `MAIL_MAILER`            | log              | Mail driver                |
| `APP_TIMEZONE`           | UTC              | Default application timezone |
| `LEDGER_FILESYSTEM_DISK` | local            | Storage for ledger exports |

For production with Redis, update:

```env
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

## License

MIT
