import { Head, Link, router, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ColorPicker } from '@/components/color-picker';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
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
import { formatAmount } from '@/lib/format';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import {
    edit as editRoute,
    index as accountsIndex,
    show as accountShow,
} from '@/routes/ledgers/accounts';
import type { Account, AccountType, BreadcrumbItem } from '@/types';

type ApiAccount = Omit<Account, 'accountType'> & {
    account_type?: {
        id: number;
        name: string;
        color: string | null;
        is_credit: boolean;
    };
};

export default function EditAccount({
    accountId,
}: {
    accountId: number;
}) {
    const { currentLedger: ledger } = usePage().props;
    const base = `/api/v1/ledgers/${ledger!.id}`;

    const { data: accountResponse, loading: accountLoading } = useApiQuery<{
        data: ApiAccount;
    }>(`${base}/accounts/${accountId}`);

    const { data: accountTypesResponse, loading: typesLoading } = useApiQuery<{
        data: AccountType[];
    }>(`${base}/account-types`);

    const accountTypes = accountTypesResponse?.data ?? [];
    const apiAccount = accountResponse?.data ?? null;

    // Form state - initialized once from API data
    const [formInitialized, setFormInitialized] = useState(false);
    const [formData, setFormData] = useState({
        account_type_id: '',
        name: '',
        color: '#6B7280',
        initial_balance: '0',
        statement_day: '',
        payment_due_day: '',
        include_in_totals: true,
    });
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [processing, setProcessing] = useState(false);
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [deleting, setDeleting] = useState(false);

    // Balance adjustment state
    const [newBalance, setNewBalance] = useState('');
    const [adjusting, setAdjusting] = useState(false);
    const [balanceInitialized, setBalanceInitialized] = useState(false);

    // Initialize form data once API data loads
    if (apiAccount && !formInitialized) {
        setFormData({
            account_type_id: String(apiAccount.account_type_id),
            name: apiAccount.name,
            color: apiAccount.color ?? '#6B7280',
            initial_balance: apiAccount.initial_balance,
            statement_day:
                apiAccount.statement_day != null
                    ? String(apiAccount.statement_day)
                    : '',
            payment_due_day:
                apiAccount.payment_due_day != null
                    ? String(apiAccount.payment_due_day)
                    : '',
            include_in_totals: apiAccount.include_in_totals,
        });
        setFormInitialized(true);
    }

    const currentBalance = apiAccount
        ? parseFloat(String(apiAccount.current_balance ?? '0'))
        : 0;

    // Initialize balance field once
    if (apiAccount && !balanceInitialized) {
        setNewBalance(String(currentBalance));
        setBalanceInitialized(true);
    }

    const [isCredit, setIsCredit] = useState(false);

    // Update isCredit when account types and form data are both available
    if (accountTypes.length > 0 && formData.account_type_id && !formInitialized) {
        // Handled in init above
    }

    // Sync isCredit with current selection
    const selectedType = accountTypes.find(
        (t) => String(t.id) === formData.account_type_id,
    );
    const effectiveIsCredit = selectedType?.is_credit ?? isCredit;

    const loading = accountLoading || typesLoading;

    const breadcrumbs: BreadcrumbItem[] = apiAccount
        ? [
              {
                  title: ledger!.name,
                  href: ledgerDashboard.url(ledger!.id),
              },
              {
                  title: 'Accounts',
                  href: accountsIndex.url(ledger!.id),
              },
              {
                  title: apiAccount.name,
                  href: accountShow.url({
                      ledger: ledger!.id,
                      account: apiAccount.id,
                  }),
              },
              {
                  title: 'Edit',
                  href: editRoute.url({
                      ledger: ledger!.id,
                      account: apiAccount.id,
                  }),
              },
          ]
        : [
              {
                  title: ledger!.name,
                  href: ledgerDashboard.url(ledger!.id),
              },
              {
                  title: 'Accounts',
                  href: accountsIndex.url(ledger!.id),
              },
          ];

    function updateField(field: string, value: string | boolean) {
        setFormData((prev) => ({ ...prev, [field]: value }));
        setErrors((prev) => {
            const updated = { ...prev };
            delete updated[field];

            return updated;
        });
    }

    function handleAccountTypeChange(value: string) {
        updateField('account_type_id', value);
        const typeId = parseInt(value, 10);
        const type = accountTypes.find((t) => t.id === typeId);
        setIsCredit(type?.is_credit ?? false);
    }

    function handleDelete() {
        setDeleting(true);
        api.delete(`${base}/accounts/${accountId}`)
            .then(() => {
                toast.success('Account deleted');
                router.visit(accountsIndex.url(ledger!.id));
            })
            .catch(() => {
                setDeleting(false);
                toast.error('Failed to delete account');
            });
        setShowDeleteDialog(false);
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        api.put(`${base}/accounts/${accountId}`, {
            body: {
                account_type_id: formData.account_type_id,
                name: formData.name,
                color: formData.color,
                initial_balance: formData.initial_balance,
                include_in_totals: formData.include_in_totals,
                statement_day: effectiveIsCredit
                    ? formData.statement_day || null
                    : null,
                payment_due_day: effectiveIsCredit
                    ? formData.payment_due_day || null
                    : null,
            },
        })
            .then(() => {
                toast.success('Account updated');
                router.visit(
                    accountShow.url({
                        ledger: ledger!.id,
                        account: accountId,
                    }),
                );
            })
            .catch((err: unknown) => {
                setProcessing(false);

                if (err instanceof ApiError && err.isValidationError) {
                    setErrors(err.validationErrors);
                } else {
                    toast.error('Failed to update account');
                }
            });
    }

    function handleAdjustBalance() {
        setAdjusting(true);

        const diff = parseFloat(newBalance) - currentBalance;

        api.post(`${base}/accounts/${accountId}/adjust-balance`, {
            body: {
                amount: diff,
                description: 'Balance adjustment',
            },
        })
            .then(() => {
                setAdjusting(false);
                toast.success('Balance adjusted');
                router.reload();
            })
            .catch(() => {
                setAdjusting(false);
                toast.error('Failed to adjust balance');
            });
    }

    if (loading) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Edit account" />
                <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4">
                    <Skeleton className="h-8 w-48" />
                    <div className="space-y-6 rounded-xl border border-sidebar-border/70 p-6">
                        {[1, 2, 3, 4, 5].map((i) => (
                            <div key={i} className="grid gap-2">
                                <Skeleton className="h-4 w-24" />
                                <Skeleton className="h-10 w-full" />
                            </div>
                        ))}
                    </div>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${apiAccount?.name ?? 'account'}`} />

            <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Edit account"
                        description="Update the account details."
                    />

                    <Dialog
                        open={showDeleteDialog}
                        onOpenChange={setShowDeleteDialog}
                    >
                        <DialogTrigger asChild>
                            <Button variant="destructive" size="sm">
                                Delete account
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Delete account</DialogTitle>
                                <DialogDescription>
                                    This will delete all transactions for this
                                    account. This action cannot be undone.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <Button
                                    variant="outline"
                                    onClick={() => setShowDeleteDialog(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    variant="destructive"
                                    onClick={handleDelete}
                                    disabled={deleting}
                                >
                                    Delete account
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>

                <form
                    onSubmit={submit}
                    className="space-y-6 rounded-xl border border-sidebar-border/70 p-6"
                >
                    {/* Account type */}
                    <div className="grid gap-2">
                        <Label htmlFor="account_type_id">Account type</Label>
                        <Select
                            value={formData.account_type_id}
                            onValueChange={handleAccountTypeChange}
                        >
                            <SelectTrigger
                                id="account_type_id"
                                className="w-full"
                            >
                                <SelectValue placeholder="Select a type" />
                            </SelectTrigger>
                            <SelectContent>
                                {accountTypes.map((type) => (
                                    <SelectItem
                                        key={type.id}
                                        value={String(type.id)}
                                    >
                                        {type.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.account_type_id?.[0]} />
                    </div>

                    {/* Name */}
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
                            autoFocus
                        />
                        <InputError message={errors.name?.[0]} />
                    </div>

                    {/* Color */}
                    <div className="grid gap-2">
                        <Label>Color</Label>
                        <ColorPicker
                            value={formData.color}
                            onChange={(color) => updateField('color', color)}
                        />
                        <InputError message={errors.color?.[0]} />
                        <p className="text-xs text-muted-foreground">
                            Choose a color to identify this account.
                        </p>
                    </div>

                    {/* Initial balance */}
                    <div className="grid gap-2">
                        <Label htmlFor="initial_balance">Initial balance</Label>
                        <Input
                            id="initial_balance"
                            name="initial_balance"
                            type="number"
                            inputMode="decimal"
                            step="0.01"
                            value={formData.initial_balance}
                            onChange={(e) =>
                                updateField('initial_balance', e.target.value)
                            }
                            required
                        />
                        <InputError message={errors.initial_balance?.[0]} />
                    </div>

                    {/* Statement day (credit accounts only) */}
                    {effectiveIsCredit && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="statement_day">
                                    Statement day{' '}
                                    <span className="text-muted-foreground">
                                        (1-31)
                                    </span>
                                </Label>
                                <Input
                                    id="statement_day"
                                    name="statement_day"
                                    type="number"
                                    inputMode="decimal"
                                    min="1"
                                    max="31"
                                    value={formData.statement_day}
                                    onChange={(e) =>
                                        updateField(
                                            'statement_day',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. 15"
                                />
                                <InputError
                                    message={errors.statement_day?.[0]}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="payment_due_day">
                                    Payment due day{' '}
                                    <span className="text-muted-foreground">
                                        (1-31)
                                    </span>
                                </Label>
                                <Input
                                    id="payment_due_day"
                                    name="payment_due_day"
                                    type="number"
                                    inputMode="decimal"
                                    min="1"
                                    max="31"
                                    value={formData.payment_due_day}
                                    onChange={(e) =>
                                        updateField(
                                            'payment_due_day',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. 25"
                                />
                                <InputError
                                    message={errors.payment_due_day?.[0]}
                                />
                            </div>
                        </>
                    )}

                    {/* Include in totals */}
                    <div className="flex items-center gap-3">
                        <Checkbox
                            id="include_in_totals"
                            checked={formData.include_in_totals}
                            onCheckedChange={(checked) =>
                                updateField(
                                    'include_in_totals',
                                    checked === true,
                                )
                            }
                        />
                        <Label htmlFor="include_in_totals">
                            Include in totals
                        </Label>
                    </div>

                    <div className="flex items-center gap-3">
                        <Button disabled={processing}>Save changes</Button>
                        <Link
                            href={accountShow.url({
                                ledger: ledger!.id,
                                account: accountId,
                            })}
                            className="text-sm text-muted-foreground hover:underline"
                        >
                            Cancel
                        </Link>
                    </div>
                </form>

                <div className="space-y-4 rounded-xl border border-sidebar-border/70 p-6">
                    <div>
                        <h3 className="text-sm font-medium">Adjust balance</h3>
                        <p className="text-xs text-muted-foreground">
                            Set the current balance. An adjustment transaction
                            will be created for the difference.
                        </p>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="new_balance">
                            Current balance:{' '}
                            <span className="font-mono">
                                {formatAmount(currentBalance)}
                            </span>
                        </Label>
                        <Input
                            id="new_balance"
                            type="number"
                            inputMode="decimal"
                            step="0.01"
                            value={newBalance}
                            onChange={(e) => setNewBalance(e.target.value)}
                        />
                    </div>

                    {(() => {
                        const diff = parseFloat(newBalance) - currentBalance;
                        const hasDiff =
                            !isNaN(diff) && Math.abs(diff) >= 0.01;

                        return hasDiff ? (
                            <p className="text-xs text-muted-foreground">
                                This will create a{' '}
                                <span
                                    className={
                                        diff > 0
                                            ? 'font-medium text-green-600'
                                            : 'font-medium text-red-600'
                                    }
                                >
                                    {diff > 0 ? '+' : ''}
                                    {formatAmount(diff)}
                                </span>{' '}
                                {diff > 0 ? 'income' : 'expense'} adjustment
                                transaction.
                            </p>
                        ) : null;
                    })()}

                    <Button
                        disabled={
                            adjusting ||
                            isNaN(parseFloat(newBalance)) ||
                            Math.abs(parseFloat(newBalance) - currentBalance) <
                                0.01
                        }
                        onClick={handleAdjustBalance}
                    >
                        {adjusting ? 'Adjusting...' : 'Adjust balance'}
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
