import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AccountController from '@/actions/App/Http/Controllers/Ledger/AccountController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import AppLayout from '@/layouts/app-layout';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import {
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
}: {
    ledger: Ledger;
    account: Account;
    accountTypes: AccountType[];
}) {
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const selectedType = accountTypes.find(
        (t) => t.id === account.account_type_id,
    );
    const [isCredit, setIsCredit] = useState(selectedType?.is_credit ?? false);

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
        router.delete(destroy.url({ ledger: ledger.id, account: account.id }));
        setShowDeleteDialog(false);
    }

    function handleAccountTypeChange(e: React.ChangeEvent<HTMLSelectElement>) {
        const typeId = parseInt(e.target.value, 10);
        const type = accountTypes.find((t) => t.id === typeId);
        setIsCredit(type?.is_credit ?? false);
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

                <Form
                    {...AccountController.update.form({
                        ledger: ledger.id,
                        account: account.id,
                    })}
                    className="space-y-6 rounded-xl border border-sidebar-border/70 p-6"
                >
                    {({ errors, processing }) => (
                        <>
                            {/* Account type */}
                            <div className="grid gap-2">
                                <Label htmlFor="account_type_id">
                                    Account type
                                </Label>
                                <select
                                    id="account_type_id"
                                    name="account_type_id"
                                    defaultValue={account.account_type_id}
                                    onChange={handleAccountTypeChange}
                                    className="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                >
                                    {accountTypes.map((type) => (
                                        <option key={type.id} value={type.id}>
                                            {type.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.account_type_id} />
                            </div>

                            {/* Name */}
                            <div className="grid gap-2">
                                <Label htmlFor="name">Account name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={account.name}
                                    required
                                    autoFocus
                                />
                                <InputError message={errors.name} />
                            </div>

                            {/* Initial balance */}
                            <div className="grid gap-2">
                                <Label htmlFor="initial_balance">
                                    Initial balance
                                </Label>
                                <Input
                                    id="initial_balance"
                                    name="initial_balance"
                                    type="number"
                                    step="0.01"
                                    defaultValue={account.initial_balance}
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
                                            (1–31)
                                        </span>
                                    </Label>
                                    <Input
                                        id="statement_day"
                                        name="statement_day"
                                        type="number"
                                        min="1"
                                        max="31"
                                        defaultValue={
                                            account.statement_day ?? undefined
                                        }
                                        placeholder="e.g. 15"
                                    />
                                    <InputError
                                        message={errors.statement_day}
                                    />
                                </div>
                            )}

                            {/* Include in totals */}
                            <div className="flex items-center gap-3">
                                <input
                                    type="hidden"
                                    name="include_in_totals"
                                    value="0"
                                />
                                <input
                                    id="include_in_totals"
                                    name="include_in_totals"
                                    type="checkbox"
                                    value="1"
                                    defaultChecked={account.include_in_totals}
                                    className="size-4 rounded border-input"
                                />
                                <Label htmlFor="include_in_totals">
                                    Include in totals
                                </Label>
                            </div>

                            <div className="flex items-center gap-3">
                                <Button disabled={processing}>
                                    Save changes
                                </Button>
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
                        </>
                    )}
                </Form>
            </div>
        </AppLayout>
    );
}
