import { Head, Link, usePage } from '@inertiajs/react';
import { dashboard, login, register } from '@/routes';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { BarChart3, CreditCard, PiggyBank, Receipt } from 'lucide-react';

const features = [
    {
        icon: CreditCard,
        title: 'Multi-Account Tracking',
        description: 'Keep tabs on all your accounts in one place — checking, savings, credit cards, and more.',
    },
    {
        icon: Receipt,
        title: 'Recurring Bill Management',
        description: 'Never miss a payment. Track recurring bills and subscriptions with automatic reminders.',
    },
    {
        icon: BarChart3,
        title: 'Visual Spending Reports',
        description: 'Understand where your money goes with clear, intuitive charts and breakdowns.',
    },
    {
        icon: PiggyBank,
        title: 'Budget Tracking',
        description: 'Set budgets by category and track your progress throughout the month.',
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
                <header className="sticky top-0 z-50 border-b bg-background/80 backdrop-blur-sm">
                    <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
                        <span className="text-xl font-bold tracking-tight">Feenans</span>
                        <nav className="flex items-center gap-2">
                            {auth.user ? (
                                <Button asChild variant="outline">
                                    <Link href={dashboard.url()}>Dashboard</Link>
                                </Button>
                            ) : (
                                <>
                                    <Button asChild variant="ghost">
                                        <Link href={login.url()}>Log in</Link>
                                    </Button>
                                    {canRegister && (
                                        <Button asChild>
                                            <Link href={register.url()}>Get Started</Link>
                                        </Button>
                                    )}
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                {/* Hero */}
                <section className="flex flex-1 flex-col items-center justify-center px-4 py-20 text-center sm:px-6 sm:py-32">
                    <h1 className="max-w-2xl text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">
                        Track your money, your way.
                    </h1>
                    <p className="mt-6 max-w-xl text-lg text-muted-foreground sm:text-xl">
                        A simple, powerful personal finance tracker built for everyday spending.
                    </p>
                    <div className="mt-10 flex flex-col items-center gap-4 sm:flex-row">
                        {auth.user ? (
                            <Button asChild size="lg">
                                <Link href={dashboard.url()}>Go to Dashboard</Link>
                            </Button>
                        ) : (
                            <>
                                {canRegister && (
                                    <Button asChild size="lg">
                                        <Link href={register.url()}>Get Started</Link>
                                    </Button>
                                )}
                                <Button asChild variant="ghost" size="lg">
                                    <Link href={login.url()}>
                                        Already have an account? Log in
                                    </Link>
                                </Button>
                            </>
                        )}
                    </div>
                </section>

                {/* Features */}
                <section className="border-t bg-muted/30 px-4 py-20 sm:px-6 sm:py-28">
                    <div className="mx-auto max-w-6xl">
                        <h2 className="mb-12 text-center text-2xl font-bold tracking-tight sm:text-3xl">
                            Everything you need to stay on top of your finances
                        </h2>
                        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            {features.map((feature) => (
                                <Card key={feature.title} className="bg-background">
                                    <CardHeader>
                                        <feature.icon className="mb-2 size-8 text-primary" />
                                        <CardTitle>{feature.title}</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-sm text-muted-foreground">
                                            {feature.description}
                                        </p>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Footer */}
                <footer className="border-t px-4 py-8 text-center text-sm text-muted-foreground sm:px-6">
                    <span>&copy; {new Date().getFullYear()} Feenans</span>
                </footer>
            </div>
        </>
    );
}
