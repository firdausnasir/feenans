import { Head, router, useForm } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
    autosave,
    complete,
    saveStep,
} from '@/actions/App/Http/Controllers/OnboardingController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import type { Account, AccountType, Ledger } from '@/types/ledger';

type OnboardingData = {
    // Step 1
    name?: string;
    cycle_start_day?: number;
    seed_categories?: boolean;
    // Step 2
    account_type_id?: number;
    account_name?: string;
    initial_balance?: number;
    statement_day?: number;
    include_in_totals?: boolean;
};

type Props = {
    step: 1 | 2 | 3;
    savedData: OnboardingData | null;
    accountTypes: AccountType[];
    ledger: Ledger | null;
    account: Account | null;
};

// ──────────────────────────────────────────
// Step 1: Create Your Ledger
// ──────────────────────────────────────────

function Step1({ savedData }: { savedData: OnboardingData | null }) {
    const form = useForm({
        name: savedData?.name ?? '',
        cycle_start_day: savedData?.cycle_start_day ?? 1,
        seed_categories: savedData?.seed_categories ?? true,
    });

    // Debounced auto-save
    const autosaveTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const scheduleAutosave = useCallback((data: typeof form.data) => {
        if (autosaveTimer.current) {
            clearTimeout(autosaveTimer.current);
        }

        autosaveTimer.current = setTimeout(() => {
            router.post(
                autosave().url,
                { data },
                { preserveState: true, preserveScroll: true },
            );
        }, 500);
    }, []);

    // Watch form data changes for auto-save
    useEffect(() => {
        scheduleAutosave(form.data);

        return () => {
            if (autosaveTimer.current) {
                clearTimeout(autosaveTimer.current);
            }
        };
    }, [form.data, scheduleAutosave]);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.post(saveStep(1).url);
    }

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="name">Ledger name</Label>
                <Input
                    id="name"
                    type="text"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    placeholder="My Finances"
                    required
                    autoFocus
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="cycle_start_day">Cycle start day</Label>
                <Input
                    id="cycle_start_day"
                    type="number"
                    min={1}
                    max={31}
                    value={form.data.cycle_start_day}
                    onChange={(e) =>
                        form.setData('cycle_start_day', Number(e.target.value))
                    }
                />
                <p className="text-sm text-muted-foreground">
                    This sets the start day of your monthly budget cycle. For
                    example, if set to 25, your month runs from the 25th to the
                    24th of the next month.
                </p>
                <InputError message={form.errors.cycle_start_day} />
            </div>

            <div className="flex items-center gap-3">
                <Checkbox
                    id="seed_categories"
                    checked={form.data.seed_categories}
                    onCheckedChange={(checked) =>
                        form.setData('seed_categories', checked === true)
                    }
                />
                <Label htmlFor="seed_categories">
                    Start with pre-seeded expense and income categories?
                </Label>
            </div>

            <Button type="submit" className="w-full" disabled={form.processing}>
                {form.processing && <Spinner />}
                Continue
            </Button>
        </form>
    );
}

// ──────────────────────────────────────────
// Step 2: Create Your First Account
// ──────────────────────────────────────────

function Step2({
    savedData,
    accountTypes,
}: {
    savedData: OnboardingData | null;
    accountTypes: AccountType[];
}) {
    const defaultAccountTypeId =
        savedData?.account_type_id ?? accountTypes[0]?.id ?? 0;

    const form = useForm({
        name: savedData?.account_name ?? '',
        account_type_id: defaultAccountTypeId,
        initial_balance: savedData?.initial_balance ?? 0,
        statement_day: savedData?.statement_day ?? 1,
        include_in_totals: savedData?.include_in_totals ?? true,
    });

    const selectedAccountType = accountTypes.find(
        (t) => t.id === form.data.account_type_id,
    );
    const isCredit = selectedAccountType?.is_credit ?? false;

    // Debounced auto-save
    const autosaveTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const scheduleAutosave = useCallback((data: typeof form.data) => {
        if (autosaveTimer.current) {
            clearTimeout(autosaveTimer.current);
        }

        autosaveTimer.current = setTimeout(() => {
            router.post(
                autosave().url,
                { data: { ...data, account_name: data.name } },
                { preserveState: true, preserveScroll: true },
            );
        }, 500);
    }, []);

    useEffect(() => {
        scheduleAutosave(form.data);

        return () => {
            if (autosaveTimer.current) {
                clearTimeout(autosaveTimer.current);
            }
        };
    }, [form.data, scheduleAutosave]);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.post(saveStep(2).url);
    }

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="account_name">Account name</Label>
                <Input
                    id="account_name"
                    type="text"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    placeholder="Checking account"
                    required
                    autoFocus
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="account_type_id">Account type</Label>
                <Select
                    value={String(form.data.account_type_id)}
                    onValueChange={(val) =>
                        form.setData('account_type_id', Number(val))
                    }
                >
                    <SelectTrigger id="account_type_id" className="w-full">
                        <SelectValue placeholder="Select a type" />
                    </SelectTrigger>
                    <SelectContent>
                        {accountTypes.map((type) => (
                            <SelectItem key={type.id} value={String(type.id)}>
                                {type.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={form.errors.account_type_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="initial_balance">Initial balance</Label>
                <Input
                    id="initial_balance"
                    type="number"
                    step="0.01"
                    value={form.data.initial_balance}
                    onChange={(e) =>
                        form.setData('initial_balance', Number(e.target.value))
                    }
                />
                <InputError message={form.errors.initial_balance} />
            </div>

            {isCredit && (
                <div className="grid gap-2">
                    <Label htmlFor="statement_day">Statement day</Label>
                    <Input
                        id="statement_day"
                        type="number"
                        min={1}
                        max={31}
                        value={form.data.statement_day}
                        onChange={(e) =>
                            form.setData(
                                'statement_day',
                                Number(e.target.value),
                            )
                        }
                    />
                    <InputError message={form.errors.statement_day} />
                </div>
            )}

            <div className="flex items-center gap-3">
                <Checkbox
                    id="include_in_totals"
                    checked={form.data.include_in_totals}
                    onCheckedChange={(checked) =>
                        form.setData('include_in_totals', checked === true)
                    }
                />
                <Label htmlFor="include_in_totals">Include in totals</Label>
            </div>

            <Button type="submit" className="w-full" disabled={form.processing}>
                {form.processing && <Spinner />}
                Continue
            </Button>
        </form>
    );
}

// ──────────────────────────────────────────
// Step 3: All Set!
// ──────────────────────────────────────────

function Step3({
    ledger,
    account,
}: {
    ledger: Ledger | null;
    account: Account | null;
}) {
    const [processing, setProcessing] = useState(false);

    function handleComplete() {
        setProcessing(true);
        router.post(complete().url);
    }

    return (
        <div className="space-y-6 text-center">
            <div className="space-y-2">
                <p className="text-lg font-medium">
                    Your ledger and first account are ready!
                </p>

                <div className="mt-4 space-y-2 rounded-lg border border-sidebar-border/70 p-4 text-left text-sm">
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">Ledger</span>
                        <span className="font-medium">
                            {ledger?.name ?? '—'}
                        </span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">Account</span>
                        <span className="font-medium">
                            {account?.name ?? '—'}
                        </span>
                    </div>
                </div>

                <p className="mt-4 text-sm text-muted-foreground">
                    You can create more accounts, categories, and payees from
                    the sidebar.
                </p>
            </div>

            <Button
                className="w-full"
                onClick={handleComplete}
                disabled={processing}
            >
                {processing && <Spinner />}
                Go to Dashboard
            </Button>
        </div>
    );
}

// ──────────────────────────────────────────
// Main page
// ──────────────────────────────────────────

const STEP_TITLES: Record<number, { title: string; description: string }> = {
    1: {
        title: 'Create your ledger',
        description: 'Set up your financial space to start tracking.',
    },
    2: {
        title: 'Create your first account',
        description: 'Add a bank account, wallet, or credit card.',
    },
    3: {
        title: "You're all set!",
        description: 'Everything is ready to go.',
    },
};

export default function Onboarding({
    step,
    savedData,
    accountTypes,
    ledger,
    account,
}: Props) {
    const { title, description } = STEP_TITLES[step] ?? STEP_TITLES[1];

    return (
        <AuthLayout title={title} description={description}>
            <Head title={`Onboarding – Step ${step} of 3`} />

            <div className="mb-6 flex items-center justify-between text-sm text-muted-foreground">
                <span>Step {step} of 3</span>
                <div className="flex gap-1.5">
                    {[1, 2, 3].map((s) => (
                        <div
                            key={s}
                            className={`h-1.5 w-8 rounded-full ${
                                s <= step ? 'bg-primary' : 'bg-muted'
                            }`}
                        />
                    ))}
                </div>
            </div>

            {step === 1 && <Step1 savedData={savedData} />}
            {step === 2 && (
                <Step2 savedData={savedData} accountTypes={accountTypes} />
            )}
            {step === 3 && <Step3 ledger={ledger} account={account} />}
        </AuthLayout>
    );
}
