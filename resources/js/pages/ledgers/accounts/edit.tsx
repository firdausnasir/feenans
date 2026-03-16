import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import AccountController from '@/actions/App/Http/Controllers/Ledger/AccountController';
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
import AppLayout from '@/layouts/app-layout';
import { formatAmount } from '@/lib/format';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import {
    adjustBalance,
    destroy,
    edit as editRoute,
    index as accountsIndex,
    show as accountShow,
} from '@/routes/ledgers/accounts';
import type { Account, AccountType, BreadcrumbItem, Ledger } from '@/types';

export default function EditAccount({
    ledger,
    account,
    accountTypes,
    currentBalance,
}: {
    ledger: Ledger;
    account: Account;
    accountTypes: AccountType[];
    currentBalance: number;
}) {
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [newBalance, setNewBalance] = useState(String(currentBalance));
    const [adjusting, setAdjusting] = useState(false);

    const selectedType = accountTypes.find(
        (t) => t.id === account.account_type_id,
    );
    const [isCredit, setIsCredit] = useState(selectedType?.is_credit ?? false);

    const { data, setData, put, processing, errors, clearErrors } = useForm({
        account_type_id: String(account.account_type_id),
        name: account.name,
        color: account.color ?? '#6B7280',
        initial_balance: account.initial_balance,
        statement_day:
            account.statement_day != null ? String(account.statement_day) : '',
        include_in_totals: account.include_in_totals,
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Accounts', href: accountsIndex.url(ledger.id) },
        {
            title: account.name,
            href: accountShow.url({ ledger: ledger.id, account: account.id }),
        },
        {
            title: 'Edit',
            href: editRoute.url({ ledger: ledger.id, account: account.id }),
        },
    ];

    function handleDelete() {
        router.delete(destroy.url({ ledger: ledger.id, account: account.id }), {
            onSuccess: () => {
                toast.success('Account deleted');
            },
        });
        setShowDeleteDialog(false);
    }

    function handleAccountTypeChange(value: string) {
        setData('account_type_id', value);
        clearErrors('account_type_id');
        const typeId = parseInt(value, 10);
        const type = accountTypes.find((t) => t.id === typeId);
        setIsCredit(type?.is_credit ?? false);
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        put(
            AccountController.update.url({
                ledger: ledger.id,
                account: account.id,
            }),
            {
                onSuccess: () => toast.success('Account updated'),
            },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${account.name}`} />

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
                            value={data.account_type_id}
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
                        <InputError message={errors.account_type_id} />
                    </div>

                    {/* Name */}
                    <div className="grid gap-2">
                        <Label htmlFor="name">Account name</Label>
                        <Input
                            id="name"
                            name="name"
                            value={data.name}
                            onChange={(e) => {
                                setData('name', e.target.value);
                                clearErrors('name');
                            }}
                            required
                            autoFocus
                        />
                        <InputError message={errors.name} />
                    </div>

                    {/* Color */}
                    <div className="grid gap-2">
                        <Label>Color</Label>
                        <ColorPicker
                            value={data.color}
                            onChange={(color) => {
                                setData('color', color);
                                clearErrors('color');
                            }}
                        />
                        <InputError message={errors.color} />
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
                            value={data.initial_balance}
                            onChange={(e) => {
                                setData('initial_balance', e.target.value);
                                clearErrors('initial_balance');
                            }}
                            required
                        />
                        <InputError message={errors.initial_balance} />
                    </div>

                    {/* Statement day (credit accounts only) */}
                    {isCredit && (
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
                                value={data.statement_day}
                                onChange={(e) => {
                                    setData('statement_day', e.target.value);
                                    clearErrors('statement_day');
                                }}
                                placeholder="e.g. 15"
                            />
                            <InputError message={errors.statement_day} />
                        </div>
                    )}

                    {/* Include in totals */}
                    <div className="flex items-center gap-3">
                        <Checkbox
                            id="include_in_totals"
                            checked={data.include_in_totals}
                            onCheckedChange={(checked) => {
                                setData('include_in_totals', checked === true);
                                clearErrors('include_in_totals');
                            }}
                        />
                        <Label htmlFor="include_in_totals">
                            Include in totals
                        </Label>
                    </div>

                    <div className="flex items-center gap-3">
                        <Button disabled={processing}>Save changes</Button>
                        <Link
                            href={accountShow.url({
                                ledger: ledger.id,
                                account: account.id,
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
                        const hasDiff = !isNaN(diff) && Math.abs(diff) >= 0.01;

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
                        onClick={() => {
                            setAdjusting(true);
                            router.post(
                                adjustBalance.url({
                                    ledger: ledger.id,
                                    account: account.id,
                                }),
                                { new_balance: newBalance },
                                {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        setAdjusting(false);
                                        toast.success('Balance adjusted');
                                    },
                                    onError: () => {
                                        setAdjusting(false);
                                        toast.error('Failed to adjust balance');
                                    },
                                },
                            );
                        }}
                    >
                        {adjusting ? 'Adjusting...' : 'Adjust balance'}
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
