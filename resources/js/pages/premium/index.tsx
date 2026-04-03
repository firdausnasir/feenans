import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    BarChart3,
    CreditCard,
    Crown,
    Layers,
    PiggyBank,
    RefreshCw,
} from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { premium } from '@/routes';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Premium', href: premium.url() },
];

const premiumFeatures = [
    {
        icon: Layers,
        title: 'Unlimited Workspaces',
        description:
            'Create multiple workspaces to organize finances for different purposes — personal, business, family, and more.',
    },
    {
        icon: BarChart3,
        title: 'Financial Reports',
        description:
            'Access detailed reports including Financial Health, Budget Performance, and Cash Flow analysis with PDF export.',
    },
    {
        icon: CreditCard,
        title: 'Unlimited Accounts',
        description:
            'Add as many bank accounts, wallets, and credit cards as you need. Free plan is limited to 7 accounts.',
    },
    {
        icon: RefreshCw,
        title: 'Recurring Transactions',
        description:
            'Set up and manage recurring bills and income. Track due dates, auto-create transactions, and never miss a payment.',
    },
    {
        icon: PiggyBank,
        title: 'Budgets',
        description:
            'Create budgets by category with weekly, monthly, or yearly periods. Get notified when spending approaches your limits.',
    },
];

export default function PremiumIndex() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Premium" />

            <div className="mx-auto flex max-w-3xl flex-col gap-8 p-4 md:p-6 lg:p-8">
                <div>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="gap-1.5"
                        onClick={() => {
                            if (window.history.length > 1) {
                                window.history.back();
                            } else {
                                router.visit('/');
                            }
                        }}
                    >
                        <ArrowLeft className="size-4" />
                        Back
                    </Button>
                </div>

                {/* Header */}
                <div className="text-center">
                    <div className="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30">
                        <Crown className="size-6 text-amber-600 dark:text-amber-400" />
                    </div>
                    <h1 className="text-2xl font-bold tracking-tight md:text-3xl">
                        Upgrade to Premium
                    </h1>
                    <p className="mt-2 text-muted-foreground">
                        Unlock the full power of your financial tracking.
                    </p>
                </div>

                {/* Features */}
                <div className="grid gap-4 sm:grid-cols-2">
                    {premiumFeatures.map((feature) => (
                        <Card key={feature.title} className="gap-2 py-4">
                            <CardHeader className="pb-0">
                                <div className="flex items-center gap-3">
                                    <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                        <feature.icon className="size-4 text-primary" />
                                    </div>
                                    <CardTitle className="text-sm font-semibold">
                                        {feature.title}
                                    </CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <CardDescription className="text-xs leading-relaxed">
                                    {feature.description}
                                </CardDescription>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {/* CTA */}
                <div className="text-center">
                    <Button
                        size="lg"
                        className="gap-2"
                        onClick={() =>
                            toast.info('Premium billing coming soon!')
                        }
                    >
                        <Crown className="size-4" />
                        Get Premium
                    </Button>
                    <p className="mt-2 text-xs text-muted-foreground">
                        Billing is not yet available. Stay tuned!
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
