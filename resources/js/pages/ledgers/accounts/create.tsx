import { Form, Head } from '@inertiajs/react';
import { toast } from 'sonner';
import AccountController from '@/actions/App/Http/Controllers/Ledger/AccountController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Accounts', href: accountsIndex.url(ledger.id) },
        { title: 'Create account', href: create.url(ledger.id) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Create ${ledger.name} account`} />

            <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Create account"
                    description="Add a new account to this ledger."
                />

                <Form
                    {...AccountController.store.form(ledger.id)}
                    className="space-y-6 rounded-xl border border-sidebar-border/70 p-6"
                    onSuccess={() => toast.success('Account created')}
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="account_type_id">
                                    Account type
                                </Label>
                                <select
                                    id="account_type_id"
                                    name="account_type_id"
                                    className="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                >
                                    {accountTypes.map((accountType) => (
                                        <option
                                            key={accountType.id}
                                            value={accountType.id}
                                        >
                                            {accountType.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.account_type_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name">Account name</Label>
                                <Input id="name" name="name" required />
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
                                    step="0.01"
                                    defaultValue="0"
                                    required
                                />
                                <InputError message={errors.initial_balance} />
                            </div>

                            <input
                                type="hidden"
                                name="include_in_totals"
                                value="1"
                            />

                            <Button disabled={processing}>
                                Create account
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </AppLayout>
    );
}
