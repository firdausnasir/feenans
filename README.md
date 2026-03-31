<p align="center">
<img src="/public/favicon.svg" alt="Feenans Logo" width="200" height="200" /><br>
</p>

<h1 align="center">Feenans</h1><br>

<p align="center">
<b>Your finances. Your rules. Nobody watching.</b>
</p>

<p align="center">
<a href="#features">Features</a>&nbsp;&bull;&nbsp;<a href="#screenshots">Screenshots</a>&nbsp;&bull;&nbsp;<a href="#installation">Installation</a>&nbsp;&bull;&nbsp;<a href="#usage">Usage</a>&nbsp;&bull;&nbsp;<a href="#contributing">Contributing</a>
</p>

<br>

<p align="center">
<b>Feenans</b> is a self-hosted personal finance tracker for people who refuse to hand their financial data to corporations. Track spending, set budgets, monitor bills, and generate reports &mdash; all on your own infrastructure where nobody else can touch your data.
</p>

<p align="center">
No telemetry. No ads. No admin access to user financial data. Just a finance app that does its job.
</p>

<br>

# Features

### Core Functionality

- Multi-account tracking &mdash; Checking, savings, credit cards, e-wallets, cash with real-time balances and net worth
- Transactions &mdash; Income, expenses, transfers with splits, attachments, bulk operations, and CSV bank import
- Categories and tags &mdash; Hierarchical categories with icons, color-coded tags, payee management
- Budgets &mdash; Per-category spending limits with rollover, progress tracking, and threshold alerts
- Recurring bills &mdash; Auto-generates transactions on schedule, flags missed payments, sends reminders
- Reports &mdash; Income/expense trends, financial health, cash flow, budget performance with PDF export
- Multi-workspace &mdash; Separate ledgers for personal, family, or project finances

### Privacy & Security

- Privacy mode &mdash; One toggle masks all amounts across the UI
- 2FA &mdash; TOTP-based two-factor authentication with recovery codes
- Audit trail &mdash; Full change history with before/after diffs
- Self-contained &mdash; No external calls, no telemetry, no tracking

### User Experience

- Dark mode &mdash; Dark, light, and system theme support
- Mobile-first &mdash; Designed for phones, enhanced for desktop

# Screenshots

Dashboard Showcase:

|           | Dark                                                                 | Light                                                                       |
| --------- | -------------------------------------------------------------------- | --------------------------------------------------------------------------- |
| Dashboard | <img src="/public/screenshots/dashboard.png" alt="Dashboard Dark" /> | <img src="/public/screenshots/dashboard-light.png" alt="Dashboard Light" /> |

<details>
<summary>Expand this to see screenshots of other pages</summary>

|            | Dark                                                                 | Light                                                                       |
| ---------- | -------------------------------------------------------------------- | --------------------------------------------------------------------------- |
| Account    | <img src="/public/screenshots/account.png" alt="Account Dark" />     | <img src="/public/screenshots/account-light.png" alt="Account Light" />     |
| Categories | <img src="/public/screenshots/category.png" alt="Categories Dark" /> | <img src="/public/screenshots/category-light.png" alt="Categories Light" /> |
| Budgets    | <img src="/public/screenshots/budget.png" alt="Budgets Dark" />      | <img src="/public/screenshots/budget-light.png" alt="Budgets Light" />      |
| Bills      | <img src="/public/screenshots/bill.png" alt="Bills Dark" />          | <img src="/public/screenshots/bill-light.png" alt="Bills Light" />          |
| Reports    | <img src="/public/screenshots/report.png" alt="Reports Dark" />      | <img src="/public/screenshots/report-light.png" alt="Reports Light" />      |

</details>

# Installation

The recommended installation method is Docker. First, prepare a directory for Feenans data and copy your `.env` file into it:

```bash
mkdir -p /opt/feenans
cp .env /opt/feenans/.env
touch /opt/feenans/database.sqlite
```

> [!NOTE]
> The `.env` file must exist before starting the containers. Copy it from `.env.example` and configure it for your environment.

To run the container via CLI, use the following command:

```bash
docker run --rm -d \
  --name feenans \
  --platform linux/amd64 \
  -p 8080:8080 \
  --env-file /opt/feenans/.env \
  -v /opt/feenans/storage:/var/www/html/storage \
  -v /opt/feenans/database.sqlite:/var/www/html/database/database.sqlite \
  ghcr.io/firdausnasir/feenans:latest
```

To use Docker Compose, use this YAML definition:

```yaml
services:
    feenans:
        image: ghcr.io/firdausnasir/feenans:latest
        platform: linux/amd64
        container_name: feenans
        restart: unless-stopped
        env_file:
            - /opt/feenans/.env
        environment:
            SSL_MODE: 'off'
            PHP_OPCACHE_ENABLE: '1'
            XDEBUG_MODE: 'off'
        volumes:
            - /opt/feenans/storage:/var/www/html/storage
            - /opt/feenans/database.sqlite:/var/www/html/database/database.sqlite
        logging:
            driver: 'json-file'
            options:
                max-file: '2'
                max-size: '10m'

    feenans-scheduler:
        image: ghcr.io/firdausnasir/feenans:latest
        platform: linux/amd64
        container_name: feenans-scheduler
        restart: unless-stopped
        entrypoint: ['php', 'artisan', 'schedule:work']
        env_file:
            - /opt/feenans/.env
        environment:
            PHP_OPCACHE_ENABLE: '1'
            XDEBUG_MODE: 'off'
        volumes:
            - /opt/feenans/storage:/var/www/html/storage
            - /opt/feenans/database.sqlite:/var/www/html/database/database.sqlite
        logging:
            driver: 'json-file'
            options:
                max-file: '2'
                max-size: '10m'

    feenans-queue:
        image: ghcr.io/firdausnasir/feenans:latest
        platform: linux/amd64
        container_name: feenans-queue
        restart: unless-stopped
        entrypoint:
            [
                'php',
                'artisan',
                'queue:work',
                '--sleep=3',
                '--tries=3',
                '--max-time=3600',
            ]
        env_file:
            - /opt/feenans/.env
        environment:
            PHP_OPCACHE_ENABLE: '1'
            XDEBUG_MODE: 'off'
        volumes:
            - /opt/feenans/storage:/var/www/html/storage
            - /opt/feenans/database.sqlite:/var/www/html/database/database.sqlite
        logging:
            driver: 'json-file'
            options:
                max-file: '2'
                max-size: '10m'
```

> [!TIP]
> The scheduler handles recurring bill generation and reminders. The queue worker processes background jobs like email notifications. Both are recommended for full functionality.

<details>
<summary>Expand this to see production and manual setup options</summary>

### Production Configuration

For production, update your `.env` with the following:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Mail (required for bill reminders and password resets)
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_FROM_ADDRESS=noreply@your-domain.com
```

### Manual Setup

**Prerequisites:** PHP 8.2+, Composer 2, Node.js 22, SQLite

```bash
git clone https://github.com/firdausnasir/feenans.git
cd feenans
composer setup
composer dev
```

`composer setup` installs dependencies, configures the environment, runs migrations, and builds frontend assets. `composer dev` starts the dev server at `http://localhost:8000`.

</details>

# Usage

Once deployed, access the web interface through your browser at `http://localhost:8080`. Create an account and start tracking your finances.

> [!NOTE]
> Feenans includes built-in authentication with TOTP-based 2FA support. No external authentication proxy is required.

# Contributing

Contributions are welcome. Please ensure they align with the project's philosophy of privacy-first, self-hosted personal finance management. Consider the following:

- Additions should have sensible defaults without breaking existing functionality
- Found a typo or need to ask a question? Please open an issue instead of a PR

# License

[GNU General Public License v3.0](LICENSE)
