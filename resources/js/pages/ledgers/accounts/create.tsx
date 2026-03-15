import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import AccountController from '@/actions/App/Http/Controllers/Ledger/AccountController';
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
import AppLayout from '@/layouts/app-layout';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import { create, index as accountsIndex } from '@/routes/ledgers/accounts';
import type { AccountType, BreadcrumbItem, Ledger } from '@/types';

export default function CreateAccount({
    ledger,
    accountTypes,
}: {
    ledger: Ledger;
    accountTypes: AccountType[];
}) {
    const { data, setData, post, processing, errors, clearErrors } = useForm({
        account_type_id: String(accountTypes[0]?.id ?? ''),
        name: '',
        initial_balance: '0',
        include_in_totals: '1',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Accounts', href: accountsIndex.url(ledger.id) },
        { title: 'Create account', href: create.url(ledger.id) },
    ];

    function submit(e: FormEvent) {
        e.preventDefault();
        post(AccountController.store.url(ledger.id), {
            onSuccess: () => toast.success('Account created'),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Create ${ledger.name} account`} />

            <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Create account"
                    description="Add a new account to this ledger."
                />

                <form
                    onSubmit={submit}
                    className="space-y-6 rounded-xl border border-sidebar-border/70 p-6"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="account_type_id">
                            Account type
                        </Label>
                        <Select
                            value={data.account_type_id}
                            onValueChange={(value) => {
                                setData('account_type_id', value);
                                clearErrors('account_type_id');
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
                        <InputError message={errors.account_type_id} />
                    </div>

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
                            placeholder="e.g., Maybank Savings, Cash Wallet"
                        />
                        <InputError message={errors.name} />
                    </div>

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
                            value={data.initial_balance}
                            onChange={(e) => {
                                setData('initial_balance', e.target.value);
                                clearErrors('initial_balance');
                            }}
                            required
                        />
                        <InputError message={errors.initial_balance} />
                        <p className="text-xs text-muted-foreground">
                            Enter your current account balance. This is
                            your starting point for tracking.
                        </p>
                    </div>

                    <Button disabled={processing}>
                        Create account
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
