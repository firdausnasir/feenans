import { Head, router, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ColorPicker } from '@/components/color-picker';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { useApiQuery } from '@/hooks/use-api-query';
import AppLayout from '@/layouts/app-layout';
import { api, ApiError } from '@/lib/api-client';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import { create, index as accountsIndex } from '@/routes/ledgers/accounts';
import type { AccountType, BreadcrumbItem } from '@/types';

export default function CreateAccount() {
    const { currentLedger: ledger } = usePage().props;
    const base = `/api/v1/ledgers/${ledger!.id}`;

    const { data: accountTypesResponse, loading: typesLoading } = useApiQuery<{
        data: AccountType[];
    }>(`${base}/account-types`);

    const accountTypes = accountTypesResponse?.data ?? [];

    const [formData, setFormData] = useState({
        account_type_id: '',
        name: '',
        color: '#6B7280',
        initial_balance: '0',
        include_in_totals: '1',
        statement_day: '',
        payment_due_day: '',
    });
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [processing, setProcessing] = useState(false);

    // Set default account type when data loads
    const defaultTypeSet = useState(false);

    if (
        !defaultTypeSet[0] &&
        accountTypes.length > 0 &&
        !formData.account_type_id
    ) {
        setFormData((prev) => ({
            ...prev,
            account_type_id: String(accountTypes[0].id),
        }));
        defaultTypeSet[1](true);
    }

    const selectedAccountType = accountTypes.find(
        (t) => String(t.id) === formData.account_type_id,
    );
    const isCreditCard = selectedAccountType?.is_credit ?? false;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger!.name, href: ledgerDashboard.url(ledger!.id) },
        { title: 'Accounts', href: accountsIndex.url(ledger!.id) },
        { title: 'Create account', href: create.url(ledger!.id) },
    ];

    function updateField(field: string, value: string) {
        setFormData((prev) => ({ ...prev, [field]: value }));
        setErrors((prev) => {
            const updated = { ...prev };
            delete updated[field];

            return updated;
        });
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        api.post(`${base}/accounts`, {
            body: {
                account_type_id: formData.account_type_id,
                name: formData.name,
                color: formData.color,
                initial_balance: formData.initial_balance,
                include_in_totals: formData.include_in_totals,
                ...(isCreditCard && formData.statement_day
                    ? { statement_day: formData.statement_day }
                    : {}),
                ...(isCreditCard && formData.payment_due_day
                    ? { payment_due_day: formData.payment_due_day }
                    : {}),
            },
        })
            .then(() => {
                toast.success('Account created');
                router.visit(accountsIndex.url(ledger!.id));
            })
            .catch((err: unknown) => {
                setProcessing(false);

                if (err instanceof ApiError && err.isValidationError) {
                    setErrors(err.validationErrors);
                } else {
                    toast.error('Failed to create account');
                }
            });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Create ${ledger!.name} account`} />

            <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Create account"
                    description="Add a new account to this ledger."
                />

                {typesLoading ? (
                    <div className="space-y-6 rounded-xl border border-sidebar-border/70 p-6">
                        {[1, 2, 3, 4].map((i) => (
                            <div key={i} className="grid gap-2">
                                <Skeleton className="h-4 w-24" />
                                <Skeleton className="h-10 w-full" />
                            </div>
                        ))}
                    </div>
                ) : (
                    <form
                        onSubmit={submit}
                        className="space-y-6 rounded-xl border border-sidebar-border/70 p-6"
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="account_type_id">
                                Account type
                            </Label>
                            <Select
                                value={formData.account_type_id}
                                onValueChange={(value) => {
                                    const newType = accountTypes.find(
                                        (t) => String(t.id) === value,
                                    );
                                    setFormData((prev) => ({
                                        ...prev,
                                        account_type_id: value,
                                        statement_day: newType?.is_credit
                                            ? prev.statement_day
                                            : '',
                                        payment_due_day: newType?.is_credit
                                            ? prev.payment_due_day
                                            : '',
                                    }));
                                    setErrors((prev) => {
                                        const updated = { ...prev };
                                        delete updated.account_type_id;

                                        return updated;
                                    });
                                }}
                            >
                                <SelectTrigger
                                    id="account_type_id"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Select a type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {accountTypes.map((accountType) => (
                                        <SelectItem
                                            key={accountType.id}
                                            value={String(accountType.id)}
                                        >
                                            {accountType.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.account_type_id?.[0]} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="name">Account name</Label>
                            <Input
                                id="name"
                                name="name"
                                value={formData.name}
                                onChange={(e) =>
                                    updateField('name', e.target.value)
                                }
                                required
                                placeholder="e.g., Maybank Savings, Cash Wallet"
                            />
                            <InputError message={errors.name?.[0]} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Color</Label>
                            <ColorPicker
                                value={formData.color}
                                onChange={(color) =>
                                    updateField('color', color)
                                }
                            />
                            <InputError message={errors.color?.[0]} />
                            <p className="text-xs text-muted-foreground">
                                Choose a color to identify this account.
                            </p>
                        </div>

                        {isCreditCard && (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="statement_day">
                                        Statement date
                                    </Label>
                                    <Select
                                        value={formData.statement_day}
                                        onValueChange={(value) =>
                                            updateField('statement_day', value)
                                        }
                                    >
                                        <SelectTrigger
                                            id="statement_day"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Select statement date" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {Array.from(
                                                { length: 31 },
                                                (_, i) => i + 1,
                                            ).map((day) => (
                                                <SelectItem
                                                    key={day}
                                                    value={String(day)}
                                                >
                                                    {day}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.statement_day?.[0]}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        The day of the month your credit card
                                        statement is generated.
                                    </p>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="payment_due_day">
                                        Payment due date
                                    </Label>
                                    <Select
                                        value={formData.payment_due_day}
                                        onValueChange={(value) =>
                                            updateField(
                                                'payment_due_day',
                                                value,
                                            )
                                        }
                                    >
                                        <SelectTrigger
                                            id="payment_due_day"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Select payment due date" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {Array.from(
                                                { length: 31 },
                                                (_, i) => i + 1,
                                            ).map((day) => (
                                                <SelectItem
                                                    key={day}
                                                    value={String(day)}
                                                >
                                                    {day}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.payment_due_day?.[0]}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        The day of the month your payment is
                                        due.
                                    </p>
                                </div>
                            </>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="initial_balance">
                                Initial balance
                            </Label>
                            <Input
                                id="initial_balance"
                                name="initial_balance"
                                type="number"
                                inputMode="decimal"
                                step="0.01"
                                value={formData.initial_balance}
                                onChange={(e) =>
                                    updateField(
                                        'initial_balance',
                                        e.target.value,
                                    )
                                }
                                required
                            />
                            <InputError message={errors.initial_balance?.[0]} />
                            <p className="text-xs text-muted-foreground">
                                Enter your current account balance. This is your
                                starting point for tracking.
                            </p>
                        </div>

                        <Button disabled={processing}>Create account</Button>
                    </form>
                )}
            </div>
        </AppLayout>
    );
}
