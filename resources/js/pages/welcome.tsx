import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BarChart3,
    Bell,
    CreditCard,
    Download,
    Eye,
    EyeOff,
    FileText,
    FolderOpen,
    KeyRound,
    Layers,
    Lock,
    PiggyBank,
    Receipt,
    RefreshCw,
    Shield,
    ShieldCheck,
    Tag,
    Trash2,
    Upload,
    UserX,
} from 'lucide-react';
import type { ComponentType, SVGAttributes } from 'react';

type Feature = {
    readonly icon: ComponentType<SVGAttributes<SVGElement>>;
    readonly title: string;
    readonly description: string;
};

const coreFeatures: readonly Feature[] = [
    {
        icon: Layers,
        title: 'Multiple Ledgers',
        description:
            'Organize your finances into separate workspaces — personal, household, side projects. Each ledger is completely independent.',
    },
    {
        icon: CreditCard,
        title: 'Multi-Account Tracking',
        description:
            'Manage checking, savings, credit cards, and cash accounts in one place. Track balances, visibility, and custom account types.',
    },
    {
        icon: Receipt,
        title: 'Smart Transactions',
        description:
            'Log income, expenses, and transfers. Split transactions across categories, attach receipts, and bulk-edit with ease.',
    },
    {
        icon: RefreshCw,
        title: 'Recurring Bills',
        description:
            'Automate bill tracking with flexible schedules — daily, weekly, monthly, or custom. Get reminders before due dates.',
    },
    {
        icon: PiggyBank,
        title: 'Budget Tracking',
        description:
            'Set category budgets with custom periods. Track progress, enable rollovers, and get alerts when you approach limits.',
    },
    {
        icon: BarChart3,
        title: 'Visual Reports',
        description:
            'Understand your spending with clear charts and breakdowns. Filter by date, category, or account. Export to PDF.',
    },
    {
        icon: Tag,
        title: 'Tags & Categories',
        description:
            'Organize with hierarchical categories and color-coded tags. Create the structure that makes sense for your life.',
    },
    {
        icon: Bell,
        title: 'Smart Notifications',
        description:
            'Bill reminders, overdue alerts, and budget warnings — all delivered automatically so nothing slips through.',
    },
    {
        icon: Upload,
        title: 'CSV Import',
        description:
            'Import transactions from your bank with reusable column mappings. No manual data entry required.',
    },
    {
        icon: Download,
        title: 'Full Data Export',
        description:
            'Export your entire ledger to JSON or transactions to CSV at any time. Your data is always portable.',
    },
    {
        icon: FileText,
        title: 'Activity Audit Trail',
        description:
            'Every change is logged — who changed what, when, and the before/after values. Full transparency into your data.',
    },
    {
        icon: FolderOpen,
        title: 'REST API Access',
        description:
            'Build your own integrations with a token-based API. Manage transactions, accounts, and more programmatically.',
    },
] as const;

type PrivacyItem = {
    readonly icon: ComponentType<SVGAttributes<SVGElement>>;
    readonly title: string;
    readonly description: string;
};

const privacyItems: readonly PrivacyItem[] = [
    {
        icon: UserX,
        title: 'No Admin Surveillance',
        description:
            'There is no admin panel. No one — not even the system operator — can browse, view, or access your financial data.',
    },
    {
        icon: EyeOff,
        title: 'Complete Data Isolation',
        description:
            'Your ledgers, transactions, and accounts are invisible to other users. Every query is scoped to your account only.',
    },
    {
        icon: Trash2,
        title: 'Full Deletion Rights',
        description:
            'Delete your account and all associated data at any time. Soft-deleted items are yours to restore or permanently remove.',
    },
    {
        icon: Download,
        title: 'Data Portability',
        description:
            'Export everything — accounts, transactions, categories, budgets, bills, tags — in a single JSON file. No lock-in, ever.',
    },
] as const;

type SecurityItem = {
    readonly icon: ComponentType<SVGAttributes<SVGElement>>;
    readonly title: string;
    readonly description: string;
};

const securityItems: readonly SecurityItem[] = [
    {
        icon: KeyRound,
        title: 'Two-Factor Authentication',
        description:
            'Protect your account with TOTP-based 2FA. Recovery codes ensure you never lose access.',
    },
    {
        icon: Lock,
        title: 'Token-Based API Security',
        description:
            'API access uses Sanctum tokens scoped to individual ledgers with built-in rate limiting.',
    },
    {
        icon: Shield,
        title: 'Policy-Based Authorization',
        description:
            'Every action is checked against authorization policies. You can only access data you own.',
    },
    {
        icon: Eye,
        title: 'Email Verification',
        description:
            'Account verification ensures only real users access the platform. Password confirmation guards sensitive operations.',
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
                                                Get Started
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
                        <span>
                            Private by design. No admin access to your data.
                        </span>
                    </div>
                    <h1 className="max-w-3xl text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">
                        Your finances. Your control.{' '}
                        <span className="text-muted-foreground">
                            No one watching.
                        </span>
                    </h1>
                    <p className="mt-6 max-w-2xl text-lg leading-relaxed text-muted-foreground sm:text-xl">
                        A powerful, private personal finance tracker with no
                        admin dashboard, no data snooping, and no compromises.
                        Track spending, manage budgets, and stay on top of bills
                        — all on your terms.
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
                                            Start Tracking for Free
                                            <ArrowRight className="ml-2 size-4" />
                                        </Link>
                                    </Button>
                                )}
                                <Button asChild variant="outline" size="lg">
                                    <Link href={login.url()}>
                                        Already have an account? Log in
                                    </Link>
                                </Button>
                            </>
                        )}
                    </div>
                </section>

                {/* Trust Banner */}
                <section className="border-y bg-muted/30 px-4 py-6 sm:px-6">
                    <div className="mx-auto flex max-w-4xl flex-wrap items-center justify-center gap-x-8 gap-y-3 text-sm text-muted-foreground">
                        <div className="flex items-center gap-2">
                            <ShieldCheck className="size-4 text-primary" />
                            <span>Two-factor authentication</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <UserX className="size-4 text-primary" />
                            <span>No admin panel</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <Lock className="size-4 text-primary" />
                            <span>Encrypted sessions</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <Download className="size-4 text-primary" />
                            <span>Full data export</span>
                        </div>
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
                                        no admin panel
                                    </strong>
                                    . There is no backdoor, no dashboard where
                                    someone can browse your transactions, and no
                                    way for anyone to access your financial
                                    data. Every piece of information you enter
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
                                    encrypted sessions, CSRF protection, rate
                                    limiting, and scoped API tokens are all
                                    built in — not bolted on.
                                </p>
                                <p className="mt-4 leading-relaxed text-muted-foreground">
                                    Authorization policies enforce strict data
                                    boundaries. API tokens are scoped to
                                    individual ledgers with rate limits.
                                    Sensitive actions require password
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
                            Take control of your finances today
                        </h2>
                        <p className="mt-4 text-lg text-muted-foreground">
                            No credit card required. No data sold. No admin
                            watching. Just a simple, secure way to manage your
                            money.
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
                                                Get Started for Free
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
