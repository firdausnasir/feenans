import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCheck,
    Download,
    EyeOff,
    FolderOpen,
    History,
    Key,
    Lock,
    Paperclip,
    ShieldCheck,
    Upload,
} from 'lucide-react';
import type { ComponentType, SVGAttributes } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import ScreenshotImage from '@/components/screenshot-image';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

type GalleryFeature = {
    readonly screenshot: string;
    readonly title: string;
    readonly description: string;
};

const galleryFeatures: readonly GalleryFeature[] = [
    {
        screenshot: 'report',
        title: 'In-Depth Reports',
        description:
            'Four report views — income vs. expense trends, financial health with net worth tracking, budget performance, and daily cash flow. Spending heatmaps, category breakdowns, payee analysis, and one-click PDF export.',
    },
    {
        screenshot: 'bill',
        title: 'Recurring Bill Tracker',
        description:
            'Never miss a subscription or bill again. Set flexible schedules — daily, weekly, monthly, yearly, or custom intervals. Transactions auto-generate on due dates, and missed payments get flagged instantly.',
    },
    {
        screenshot: 'budget',
        title: 'Budget Goals',
        description:
            'Set spending limits per category with visual progress bars that shift color as you approach your limit. Supports rollover, custom periods, and follows your billing cycle — not just calendar months.',
    },
    {
        screenshot: 'account',
        title: 'Multi-Account Tracking',
        description:
            'Bank accounts, credit cards, e-wallets, and cash — all in one place. Real-time balances, color-coded account types, net worth overview, and statement cycle tracking.',
    },
    {
        screenshot: 'category',
        title: 'Hierarchical Categories',
        description:
            'Organize spending with two-level categories, custom colors, and drag-and-drop reordering. See transaction counts per category for instant insight into where your money goes.',
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

type CycleOption = {
    readonly label: string;
    readonly dates: string;
    readonly active?: boolean;
};

const cycleOptions: readonly CycleOption[] = [
    { label: 'Standard Month', dates: '1 Mar 2026 – 31 Mar 2026' },
    { label: 'Salary Day (25th)', dates: '25 Feb 2026 – 24 Mar 2026' },
    {
        label: 'Credit Card Cycle (16th)',
        dates: '16 Feb 2026 – 15 Mar 2026',
        active: true,
    },
    { label: 'Custom (any day)', dates: 'Pick your own start date' },
] as const;

const moreFeatures: readonly TrustBannerItem[] = [
    {
        icon: Upload,
        title: 'CSV Bank Import',
        description:
            'Upload any bank CSV and map columns with a guided wizard. Save mappings for repeat imports.',
    },
    {
        icon: FolderOpen,
        title: 'Multi-Workspace',
        description:
            'Separate workspaces for personal finances, family budgets, or side projects — each fully isolated.',
    },
    {
        icon: EyeOff,
        title: 'Privacy Mode',
        description:
            'One toggle masks every amount in the UI. Use your finance app on the train, in a cafe, anywhere.',
    },
    {
        icon: History,
        title: 'Activity Audit Trail',
        description:
            'Every change tracked with before/after diffs. Filter by type and action for complete accountability.',
    },
    {
        icon: CheckCheck,
        title: 'Bulk Operations',
        description:
            'Select multiple transactions and update categories, accounts, or delete — all in one action.',
    },
    {
        icon: Paperclip,
        title: 'Receipt Attachments',
        description:
            'Attach receipts and documents directly to transactions. Everything stays in one place.',
    },
] as const;

const trustGridItems: readonly TrustBannerItem[] = [
    {
        icon: ShieldCheck,
        title: 'No Admin Access',
        description:
            'No interface can browse, search, or export your ledgers or transactions.',
    },
    {
        icon: Lock,
        title: 'Data Isolation',
        description:
            'Every query scoped to your account. Your data never mingles with others.',
    },
    {
        icon: Key,
        title: 'Two-Factor Auth',
        description:
            'TOTP-based 2FA with backup recovery codes. Password confirmation for sensitive actions.',
    },
    {
        icon: Download,
        title: 'Full Data Portability',
        description:
            'Export everything as JSON or CSV anytime. Delete your account permanently with one click.',
    },
] as const;

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
                <header className="sticky top-0 z-50 border-b border-border bg-background/80 backdrop-blur-sm">
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
                <section className="px-4 py-16 sm:px-6 sm:py-24 lg:py-32">
                    <div className="mx-auto grid max-w-6xl items-center gap-12 lg:grid-cols-2 lg:gap-16">
                        <div>
                            <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-border bg-muted/50 px-4 py-1.5 text-sm text-muted-foreground">
                                <ShieldCheck className="size-4" />
                                <span>Privacy-first personal finance</span>
                            </div>
                            <h1 className="text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">
                                Your finances. Your rules.{' '}
                                <span className="text-muted-foreground">
                                    Nobody watching.
                                </span>
                            </h1>
                            <p className="mt-6 max-w-lg text-lg leading-relaxed text-muted-foreground">
                                Track spending, plan budgets, and understand
                                your financial health. No ads, no data mining,
                                no admin looking over your shoulder.
                            </p>
                            <div className="mt-8 flex flex-col items-start gap-3 sm:flex-row">
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
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="lg"
                                        >
                                            <Link href={login.url()}>
                                                See how it works
                                            </Link>
                                        </Button>
                                    </>
                                )}
                            </div>
                        </div>
                        <div className="relative">
                            <ScreenshotImage
                                name="dashboard"
                                alt="Feenans dashboard screenshot showing an overview of accounts, budgets, and recent transactions."
                                className="w-full rounded-xl border border-border shadow-2xl"
                            />
                        </div>
                    </div>
                </section>

                {/* Trust Banner */}
                <section className="border-y border-border bg-muted/30 px-4 py-6 sm:px-6">
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
                <section className="px-4 py-16 sm:px-6 sm:py-24">
                    <div className="mx-auto max-w-6xl">
                        <div className="mx-auto mb-16 max-w-2xl text-center">
                            <p className="mb-2 text-sm font-medium tracking-wide text-primary uppercase">
                                Features
                            </p>
                            <h2 className="text-2xl font-bold tracking-tight sm:text-3xl">
                                Everything you need, nothing you don't
                            </h2>
                            <p className="mt-4 text-muted-foreground">
                                From daily tracking to long-term budgeting.
                            </p>
                        </div>
                        <div className="flex flex-col gap-20 sm:gap-28">
                            {galleryFeatures.map((feature, index) => (
                                <div
                                    key={feature.screenshot}
                                    className={`grid items-center gap-8 lg:grid-cols-2 lg:gap-16 ${
                                        index % 2 === 1
                                            ? 'lg:[&>:first-child]:order-2'
                                            : ''
                                    }`}
                                >
                                    <div>
                                        <ScreenshotImage
                                            name={feature.screenshot}
                                            alt=""
                                            loading="lazy"
                                            decoding="async"
                                            className="w-full rounded-xl border border-border shadow-2xl"
                                        />
                                    </div>
                                    <div>
                                        <h3 className="text-xl font-semibold tracking-tight sm:text-2xl">
                                            {feature.title}
                                        </h3>
                                        <p className="mt-3 leading-relaxed text-muted-foreground">
                                            {feature.description}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* More Features */}
                <section className="border-y border-border bg-muted/30 px-4 py-16 sm:px-6 sm:py-24">
                    <div className="mx-auto max-w-6xl">
                        <div className="mx-auto mb-12 max-w-2xl text-center">
                            <p className="mb-2 text-sm font-medium tracking-wide text-primary uppercase">
                                And more
                            </p>
                            <h2 className="text-2xl font-bold tracking-tight sm:text-3xl">
                                Built for how you actually manage money
                            </h2>
                            <p className="mt-4 text-muted-foreground">
                                Every feature exists because tracking your
                                finances shouldn't feel like a chore.
                            </p>
                        </div>
                        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {moreFeatures.map((feature) => (
                                <div
                                    key={feature.title}
                                    className="rounded-lg border border-border bg-card p-6"
                                >
                                    <div className="mb-4 flex size-10 items-center justify-center rounded-lg bg-primary/10">
                                        <feature.icon className="size-5 text-primary" />
                                    </div>
                                    <h3 className="mb-1 font-semibold text-card-foreground">
                                        {feature.title}
                                    </h3>
                                    <p className="text-sm leading-relaxed text-muted-foreground">
                                        {feature.description}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Cycle Flexibility */}
                <section className="bg-muted/30 px-4 py-16 sm:px-6 sm:py-24">
                    <div className="mx-auto grid max-w-6xl items-center gap-12 lg:grid-cols-2 lg:gap-20">
                        <div>
                            <p className="mb-2 text-sm font-medium tracking-wide text-primary uppercase">
                                Flexible Cycles
                            </p>
                            <h2 className="text-2xl font-bold tracking-tight sm:text-3xl">
                                Your month starts{' '}
                                <span className="text-muted-foreground">
                                    when you say it does.
                                </span>
                            </h2>
                            <p className="mt-4 leading-relaxed text-muted-foreground">
                                Most finance apps assume your cycle starts on
                                the 1st. Feenans lets you set any start date —
                                match your salary day, your credit card billing
                                cycle, or whatever works for you. All budgets,
                                reports, and summaries follow your custom cycle.
                            </p>
                        </div>
                        <div className="flex flex-col gap-3">
                            {cycleOptions.map((option) => (
                                <div
                                    key={option.label}
                                    className={`flex items-center justify-between rounded-lg border p-4 ${
                                        option.active
                                            ? 'border-primary bg-accent/50'
                                            : 'border-border bg-card'
                                    }`}
                                >
                                    <div>
                                        <p className="font-medium text-foreground">
                                            {option.label}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {option.dates}
                                        </p>
                                    </div>
                                    <div
                                        className={`flex size-5 items-center justify-center rounded-full border-2 ${
                                            option.active
                                                ? 'border-primary bg-primary'
                                                : 'border-muted-foreground/30'
                                        }`}
                                    >
                                        {option.active && (
                                            <div className="size-2 rounded-full bg-primary-foreground" />
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Privacy & Security */}
                <section className="bg-muted/30 px-4 py-16 sm:px-6 sm:py-24">
                    <div className="mx-auto max-w-6xl">
                        <div className="mx-auto mb-12 max-w-2xl text-center">
                            <p className="mb-2 text-sm font-medium tracking-wide text-primary uppercase">
                                Privacy & Security
                            </p>
                            <h2 className="text-2xl font-bold tracking-tight sm:text-3xl">
                                Your data stays yours. Period.
                            </h2>
                            <p className="mt-4 text-muted-foreground">
                                Built secure from the ground up. No admin
                                access, no data mining, full encryption.
                            </p>
                        </div>
                        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            {trustGridItems.map((item) => (
                                <div
                                    key={item.title}
                                    className="rounded-lg border border-border bg-card p-6"
                                >
                                    <div className="mb-4 flex size-10 items-center justify-center rounded-lg bg-primary/10">
                                        <item.icon className="size-5 text-primary" />
                                    </div>
                                    <h3 className="mb-1 font-semibold text-card-foreground">
                                        {item.title}
                                    </h3>
                                    <p className="text-sm leading-relaxed text-muted-foreground">
                                        {item.description}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* CTA */}
                <section className="px-4 py-16 sm:px-6 sm:py-24">
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
                <footer className="border-t border-border px-4 py-8 sm:px-6">
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
