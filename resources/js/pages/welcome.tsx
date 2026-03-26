import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    ArrowRight,
    BarChart2,
    Bell,
    CreditCard,
    Download,
    FolderOpen,
    History,
    Key,
    Lock,
    RefreshCw,
    Shield,
    ShieldCheck,
    Tag,
    Target,
    TrendingUp,
    Upload,
    Users,
} from 'lucide-react';
import type { ComponentType, SVGAttributes } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

type Feature = {
    readonly icon: ComponentType<SVGAttributes<SVGElement>>;
    readonly title: string;
    readonly description: string;
};

const coreFeatures: readonly Feature[] = [
    {
        icon: FolderOpen,
        title: 'Multiple Ledgers',
        description:
            'Keep personal, household, and business finances completely separate. Each ledger has its own accounts, categories, budgets, and reports — with its own currency and billing cycle.',
    },
    {
        icon: CreditCard,
        title: 'Multi-Account Tracking',
        description:
            'Track checking, savings, credit cards, investments, and cash in one place. Color-coded accounts with real-time balances. Exclude accounts from net worth if needed.',
    },
    {
        icon: ArrowLeftRight,
        title: 'Smart Transactions',
        description:
            'Log income, expenses, and transfers with full metadata: payee, category, tags, notes, and file attachments. Split transactions across multiple categories. Bulk-edit hundreds of entries in one action.',
    },
    {
        icon: RefreshCw,
        title: 'Recurring Bills',
        description:
            'Track every recurring payment — rent, subscriptions, loans. Daily, weekly, monthly, or fully custom schedules. Auto-creates transactions on the due date and flags missed payments on your dashboard.',
    },
    {
        icon: Target,
        title: 'Budget Tracking',
        description:
            'Set spending limits per category with rollover support. Visual progress bars turn yellow, orange, then red as you approach your limit. Threshold alerts fire the moment you get close.',
    },
    {
        icon: BarChart2,
        title: 'Four Powerful Reports',
        description:
            'Income and expense trends, period-over-period comparison, a year-long spending heatmap, and a financial health dashboard with savings rate and debt-to-asset ratio. Export any report as a PDF.',
    },
    {
        icon: TrendingUp,
        title: 'Cash Flow Forecast',
        description:
            'See your daily cash flow and overlay upcoming bills to forecast your balance before money leaves your account. Know what is coming, not just what has happened.',
    },
    {
        icon: Users,
        title: 'Payee Management',
        description:
            'A searchable directory of every payee in your ledger. See how much you have spent with each one. Filter your entire transaction history to any payee in one click.',
    },
    {
        icon: Tag,
        title: 'Tags and Categories',
        description:
            'Two-level category hierarchy with custom icons and colors. Attach multiple tags to any transaction for cross-cutting labels like tax-deductible or reimbursable — without changing the category.',
    },
    {
        icon: Upload,
        title: 'CSV Import',
        description:
            'Import transactions from any bank. Maybank, CIMB, RHB, and Public Bank formats are auto-detected. Map columns once, save the mapping, and reuse it every time.',
    },
    {
        icon: Bell,
        title: 'Smart Notifications',
        description:
            'Automatic reminders for bills due today and overdue payments. Budget alerts the moment spending crosses your threshold. All surfaced on your dashboard, nothing buried in settings.',
    },
    {
        icon: History,
        title: 'Activity Audit Trail',
        description:
            'Every change to your data is logged with a full before-and-after diff. See exactly what changed, when, and confirm nothing was altered without your knowledge.',
    },
] as const;

type PrivacyItem = {
    readonly icon: ComponentType<SVGAttributes<SVGElement>>;
    readonly title: string;
    readonly description: string;
};

const privacyItems: readonly PrivacyItem[] = [
    {
        icon: ShieldCheck,
        title: 'No Admin Access to Your Data',
        description:
            'Operators can manage memberships and view aggregate service metrics, but no admin interface can browse, search, or export your ledgers, transactions, or budgets. Your financial data is invisible to everyone but you.',
    },
    {
        icon: Lock,
        title: 'Complete Data Isolation',
        description:
            "Every database query is scoped to your account through policy-based authorization. Your data never appears in another user's query results — not even by accident.",
    },
    {
        icon: Shield,
        title: 'Full Deletion Rights',
        description:
            'Delete your account from settings at any time. Your data is gone — permanently. No soft deletes, no retention period, no "we may keep anonymized data" clause.',
    },
    {
        icon: Download,
        title: 'Data Portability',
        description:
            'Export your entire ledger as a structured JSON file or download filtered transaction CSVs at any time. Your financial history belongs to you, not us.',
    },
] as const;

type SecurityItem = {
    readonly icon: ComponentType<SVGAttributes<SVGElement>>;
    readonly title: string;
    readonly description: string;
};

const securityItems: readonly SecurityItem[] = [
    {
        icon: Key,
        title: 'Two-Factor Authentication',
        description:
            'Protect your account with TOTP-based 2FA (Google Authenticator, Authy, or any authenticator app). Backup recovery codes included. Password confirmation required before any change to 2FA settings.',
    },
    {
        icon: Lock,
        title: 'Session-Based Protection',
        description:
            'Authentication is managed server-side using secure sessions. Sensitive actions require password confirmation, not just an active session.',
    },
    {
        icon: Shield,
        title: 'Email Verification',
        description:
            'Every new account must verify their email address before accessing ledger data. No way to access your finances from an unverified address.',
    },
    {
        icon: Upload,
        title: 'Secure File Handling',
        description:
            'Transaction attachments (receipts, invoices) are served through the application — never via public URLs. Your documents are not accessible without authentication.',
    },
] as const;

type TrustBannerItem = {
    readonly icon: ComponentType<SVGAttributes<SVGElement>>;
    readonly title: string;
    readonly description: string;
};

const trustBannerItems: readonly TrustBannerItem[] = [
    {
        icon: ShieldCheck,
        title: 'No admin access to your data',
        description:
            'Operators can never browse your transactions, ledgers, or budgets.',
    },
    {
        icon: Lock,
        title: 'End-to-end data isolation',
        description:
            "Every query is scoped to your account — your data never mingles with anyone else's.",
    },
    {
        icon: Download,
        title: 'Export anytime',
        description:
            'Take your data with you. Full JSON export and CSV downloads, any time.',
    },
    {
        icon: Key,
        title: 'Two-factor security',
        description:
            'Protect your account with TOTP-based two-factor authentication.',
    },
] as const;

function FeatureCard({ icon: Icon, title, description }: Feature) {
    return (
        <div className="group rounded-lg border border-border bg-card p-6 transition-colors hover:border-primary/20 hover:bg-accent/50">
            <div className="mb-4 flex size-10 items-center justify-center rounded-lg bg-primary/10">
                <Icon className="size-5 text-primary" />
            </div>
            <h3 className="mb-2 font-semibold text-card-foreground">{title}</h3>
            <p className="text-sm leading-relaxed text-muted-foreground">
                {description}
            </p>
        </div>
    );
}

function TrustCard({
    icon: Icon,
    title,
    description,
}: PrivacyItem | SecurityItem) {
    return (
        <div className="flex gap-4">
            <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                <Icon className="size-5 text-primary" />
            </div>
            <div>
                <h3 className="mb-1 font-semibold text-foreground">{title}</h3>
                <p className="text-sm leading-relaxed text-muted-foreground">
                    {description}
                </p>
            </div>
        </div>
    );
}

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Welcome" />
            <div className="flex min-h-screen flex-col bg-background text-foreground">
                {/* Header */}
                <header className="sticky top-0 z-50 border-b bg-background/80 backdrop-blur-sm">
                    <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center gap-2">
                            <div className="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                                <AppLogoIcon className="size-5 fill-current" />
                            </div>
                            <span className="text-xl font-bold tracking-tight">
                                Feenans
                            </span>
                        </div>
                        <nav className="flex items-center gap-2">
                            {auth.user ? (
                                <Button asChild variant="outline">
                                    <Link href={dashboard.url()}>
                                        Dashboard
                                    </Link>
                                </Button>
                            ) : (
                                <>
                                    <Button asChild variant="ghost">
                                        <Link href={login.url()}>Log in</Link>
                                    </Button>
                                    {canRegister && (
                                        <Button asChild>
                                            <Link href={register.url()}>
                                                Get started free
                                            </Link>
                                        </Button>
                                    )}
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                {/* Hero */}
                <section className="flex flex-col items-center justify-center px-4 py-20 text-center sm:px-6 sm:py-32">
                    <div className="mb-6 inline-flex items-center gap-2 rounded-full border bg-muted/50 px-4 py-1.5 text-sm text-muted-foreground">
                        <ShieldCheck className="size-4" />
                        <span>Privacy-first personal finance</span>
                    </div>
                    <h1 className="max-w-3xl text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">
                        Your finances. Your rules.{' '}
                        <span className="text-muted-foreground">
                            Nobody watching.
                        </span>
                    </h1>
                    <p className="mt-6 max-w-2xl text-lg leading-relaxed text-muted-foreground sm:text-xl">
                        Feenans gives you complete control over your money —
                        track spending, plan budgets, and understand your
                        financial health. No ads, no data mining, no admin
                        looking over your shoulder.
                    </p>
                    <div className="mt-10 flex flex-col items-center gap-4 sm:flex-row">
                        {auth.user ? (
                            <Button asChild size="lg">
                                <Link href={dashboard.url()}>
                                    Go to Dashboard
                                    <ArrowRight className="ml-2 size-4" />
                                </Link>
                            </Button>
                        ) : (
                            <>
                                {canRegister && (
                                    <Button asChild size="lg">
                                        <Link href={register.url()}>
                                            Get started free
                                            <ArrowRight className="ml-2 size-4" />
                                        </Link>
                                    </Button>
                                )}
                                <Button asChild variant="outline" size="lg">
                                    <Link href={login.url()}>
                                        See how it works
                                    </Link>
                                </Button>
                            </>
                        )}
                    </div>
                </section>

                {/* Trust Banner */}
                <section className="border-y bg-muted/30 px-4 py-6 sm:px-6">
                    <div className="mx-auto grid max-w-4xl gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {trustBannerItems.map((item) => (
                            <div
                                key={item.title}
                                className="flex items-start gap-3"
                            >
                                <item.icon className="mt-0.5 size-4 shrink-0 text-primary" />
                                <div>
                                    <p className="text-sm font-medium text-foreground">
                                        {item.title}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {item.description}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>

                {/* Features */}
                <section className="px-4 py-20 sm:px-6 sm:py-28">
                    <div className="mx-auto max-w-6xl">
                        <div className="mx-auto mb-4 max-w-2xl text-center">
                            <p className="mb-2 text-sm font-medium tracking-wide text-primary uppercase">
                                Features
                            </p>
                            <h2 className="text-2xl font-bold tracking-tight sm:text-3xl">
                                Everything you need to stay on top of your
                                finances
                            </h2>
                            <p className="mt-4 text-muted-foreground">
                                From daily tracking to long-term budgeting,
                                Feenans gives you the tools without the
                                complexity.
                            </p>
                        </div>
                        <div className="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {coreFeatures.map((feature) => (
                                <FeatureCard key={feature.title} {...feature} />
                            ))}
                        </div>
                    </div>
                </section>

                {/* Privacy Section */}
                <section className="border-t bg-muted/30 px-4 py-20 sm:px-6 sm:py-28">
                    <div className="mx-auto max-w-6xl">
                        <div className="grid items-start gap-12 lg:grid-cols-2 lg:gap-20">
                            <div>
                                <p className="mb-2 text-sm font-medium tracking-wide text-primary uppercase">
                                    Privacy
                                </p>
                                <h2 className="text-2xl font-bold tracking-tight sm:text-3xl">
                                    Your data stays yours. Period.
                                </h2>
                                <p className="mt-4 leading-relaxed text-muted-foreground">
                                    Unlike most finance apps, Feenans has{' '}
                                    <strong className="text-foreground">
                                        no admin access to your financial data
                                    </strong>
                                    . Operators can manage memberships and view
                                    aggregate service health metrics, but there
                                    is no interface that can browse, search, or
                                    export your ledgers, transactions, or
                                    budgets. Every piece of information you enter
                                    is isolated to your account and protected by
                                    policy-based access controls.
                                </p>
                                <p className="mt-4 leading-relaxed text-muted-foreground">
                                    You can export all your data at any time and
                                    permanently delete your account whenever you
                                    choose. No questions asked, no retention
                                    tricks.
                                </p>
                            </div>
                            <div className="flex flex-col gap-8">
                                {privacyItems.map((item) => (
                                    <TrustCard key={item.title} {...item} />
                                ))}
                            </div>
                        </div>
                    </div>
                </section>

                {/* Security Section */}
                <section className="border-t px-4 py-20 sm:px-6 sm:py-28">
                    <div className="mx-auto max-w-6xl">
                        <div className="grid items-start gap-12 lg:grid-cols-2 lg:gap-20">
                            <div className="order-2 flex flex-col gap-8 lg:order-1">
                                {securityItems.map((item) => (
                                    <TrustCard key={item.title} {...item} />
                                ))}
                            </div>
                            <div className="order-1 lg:order-2">
                                <p className="mb-2 text-sm font-medium tracking-wide text-primary uppercase">
                                    Security
                                </p>
                                <h2 className="text-2xl font-bold tracking-tight sm:text-3xl">
                                    Built secure from the ground up
                                </h2>
                                <p className="mt-4 leading-relaxed text-muted-foreground">
                                    Every layer of Feenans is designed with
                                    security in mind. Two-factor authentication,
                                    encrypted sessions, CSRF protection, and
                                    rate limiting on authentication endpoints
                                    are all built in — not bolted on.
                                </p>
                                <p className="mt-4 leading-relaxed text-muted-foreground">
                                    Authorization policies enforce strict data
                                    boundaries so you can only ever access data
                                    you own. Sensitive actions require password
                                    confirmation. Your financial data deserves
                                    this level of protection.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                {/* CTA */}
                <section className="border-t bg-muted/30 px-4 py-20 sm:px-6 sm:py-28">
                    <div className="mx-auto max-w-2xl text-center">
                        <h2 className="text-2xl font-bold tracking-tight sm:text-3xl">
                            Take control of your finances — privately.
                        </h2>
                        <p className="mt-4 text-lg text-muted-foreground">
                            No ads. No data mining. No admin access to your
                            ledgers. Just a clean, powerful finance tracker
                            built for people who value their privacy.
                        </p>
                        <div className="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                            {auth.user ? (
                                <Button asChild size="lg">
                                    <Link href={dashboard.url()}>
                                        Go to Dashboard
                                        <ArrowRight className="ml-2 size-4" />
                                    </Link>
                                </Button>
                            ) : (
                                <>
                                    {canRegister && (
                                        <Button asChild size="lg">
                                            <Link href={register.url()}>
                                                Create your free account
                                                <ArrowRight className="ml-2 size-4" />
                                            </Link>
                                        </Button>
                                    )}
                                    <Button asChild variant="outline" size="lg">
                                        <Link href={login.url()}>Log in</Link>
                                    </Button>
                                </>
                            )}
                        </div>
                        <p className="mt-4 text-sm text-muted-foreground">
                            No credit card required.
                        </p>
                    </div>
                </section>

                {/* Footer */}
                <footer className="border-t px-4 py-8 sm:px-6">
                    <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 sm:flex-row">
                        <div className="flex items-center gap-2">
                            <div className="flex size-6 items-center justify-center rounded bg-primary text-primary-foreground">
                                <AppLogoIcon className="size-4 fill-current" />
                            </div>
                            <span className="text-sm font-semibold">
                                Feenans
                            </span>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            &copy; {new Date().getFullYear()} Feenans. Your
                            finances, your privacy.
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}
