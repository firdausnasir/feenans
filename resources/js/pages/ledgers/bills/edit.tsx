import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import BillController from '@/actions/App/Http/Controllers/Ledger/BillController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import { edit as editRoute, index as billsIndex } from '@/routes/ledgers/bills';
import type {
    Account,
    Bill,
    BreadcrumbItem,
    Category,
    Ledger,
    Payee,
} from '@/types';

type RecurrenceType = 'daily' | 'weekly' | 'monthly' | 'yearly' | 'custom';
type EndType = 'never' | 'on_date' | 'after_occurrences';

export default function EditBill({
    ledger,
    bill,
    accounts,
    categories,
    payees,
}: {
    ledger: Ledger;
    bill: Bill;
    accounts: Account[];
    categories: Category[];
    payees: Payee[];
}) {
    const [recurrenceType, setRecurrenceType] = useState<RecurrenceType>(
        bill.recurrence_type,
    );
    const [endType, setEndType] = useState<EndType>(bill.end_type ?? 'never');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Bills', href: billsIndex.url(ledger.id) },
        {
            title: bill.name,
            href: editRoute.url({ ledger: ledger.id, bill: bill.id }),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${bill.name}`} />

            <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Edit Bill"
                    description="Update the recurring bill details."
                />

                <Form
                    {...BillController.update.form({
                        ledger: ledger.id,
                        bill: bill.id,
                    })}
                    className="space-y-6 rounded-xl border border-sidebar-border/70 p-6"
                    onSuccess={() => toast.success('Bill updated')}
                >
                    {({ errors, processing }) => (
                        <>
                            {/* Name */}
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={bill.name}
                                    required
                                    autoFocus
                                />
                                <InputError message={errors.name} />
                            </div>

                            {/* Transaction type */}
                            <div className="grid gap-2">
                                <Label htmlFor="transaction_type">Type</Label>
                                <select
                                    id="transaction_type"
                                    name="transaction_type"
                                    required
                                    defaultValue={
                                        bill.transaction_type ?? 'expense'
                                    }
                                    className="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                >
                                    <option value="expense">Expense</option>
                                    <option value="income">Income</option>
                                </select>
                                <InputError message={errors.transaction_type} />
                            </div>

                            {/* Amount */}
                            <div className="grid gap-2">
                                <Label htmlFor="amount">Amount (RM)</Label>
                                <Input
                                    id="amount"
                                    name="amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    defaultValue={bill.amount}
                                    required
                                />
                                <InputError message={errors.amount} />
                            </div>

                            {/* Account */}
                            <div className="grid gap-2">
                                <Label htmlFor="account_id">Account</Label>
                                <select
                                    id="account_id"
                                    name="account_id"
                                    defaultValue={bill.account_id}
                                    required
                                    className="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                >
                                    {accounts.map((account) => (
                                        <option
                                            key={account.id}
                                            value={account.id}
                                        >
                                            {account.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.account_id} />
                            </div>

                            {/* Category — grouped by parent */}
                            <div className="grid gap-2">
                                <Label htmlFor="category_id">
                                    Category{' '}
                                    <span className="text-muted-foreground">
                                        (optional)
                                    </span>
                                </Label>
                                <select
                                    id="category_id"
                                    name="category_id"
                                    defaultValue={bill.category_id ?? ''}
                                    className="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                >
                                    <option value="">No category</option>
                                    {categories.map((parent) =>
                                        parent.children &&
                                        parent.children.length > 0 ? (
                                            <optgroup
                                                key={parent.id}
                                                label={parent.name}
                                            >
                                                <option value={parent.id}>
                                                    {parent.name} (general)
                                                </option>
                                                {parent.children.map(
                                                    (child) => (
                                                        <option
                                                            key={child.id}
                                                            value={child.id}
                                                        >
                                                            {child.name}
                                                        </option>
                                                    ),
                                                )}
                                            </optgroup>
                                        ) : (
                                            <option
                                                key={parent.id}
                                                value={parent.id}
                                            >
                                                {parent.name}
                                            </option>
                                        ),
                                    )}
                                </select>
                                <InputError message={errors.category_id} />
                            </div>

                            {/* Payee */}
                            <div className="grid gap-2">
                                <Label htmlFor="payee_id">
                                    Payee{' '}
                                    <span className="text-muted-foreground">
                                        (optional)
                                    </span>
                                </Label>
                                <select
                                    id="payee_id"
                                    name="payee_id"
                                    defaultValue={bill.payee_id ?? ''}
                                    className="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                >
                                    <option value="">No payee</option>
                                    {payees.map((payee) => (
                                        <option key={payee.id} value={payee.id}>
                                            {payee.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.payee_id} />
                            </div>

                            {/* Recurrence */}
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="recurrence_type">
                                        Recurrence type
                                    </Label>
                                    <select
                                        id="recurrence_type"
                                        name="recurrence_type"
                                        value={recurrenceType}
                                        onChange={(e) =>
                                            setRecurrenceType(
                                                e.target
                                                    .value as RecurrenceType,
                                            )
                                        }
                                        className="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                    >
                                        <option value="daily">Daily</option>
                                        <option value="weekly">Weekly</option>
                                        <option value="monthly">Monthly</option>
                                        <option value="yearly">Yearly</option>
                                        <option value="custom">Custom</option>
                                    </select>
                                    <InputError
                                        message={errors.recurrence_type}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="recurrence_interval">
                                        Every (interval)
                                    </Label>
                                    <Input
                                        id="recurrence_interval"
                                        name="recurrence_interval"
                                        type="number"
                                        min="1"
                                        defaultValue={bill.recurrence_interval}
                                        required
                                    />
                                    <InputError
                                        message={errors.recurrence_interval}
                                    />
                                </div>
                            </div>

                            {/* Recurrence day — only relevant for monthly/custom */}
                            {(recurrenceType === 'monthly' ||
                                recurrenceType === 'custom') && (
                                <div className="grid gap-2">
                                    <Label htmlFor="recurrence_day">
                                        Day of month{' '}
                                        <span className="text-muted-foreground">
                                            (optional, 1–31)
                                        </span>
                                    </Label>
                                    <Input
                                        id="recurrence_day"
                                        name="recurrence_day"
                                        type="number"
                                        min="1"
                                        max="31"
                                        defaultValue={
                                            bill.recurrence_day ?? undefined
                                        }
                                        placeholder="e.g. 15"
                                    />
                                    <InputError
                                        message={errors.recurrence_day}
                                    />
                                </div>
                            )}

                            {/* Auto-create */}
                            <div className="flex items-center gap-3">
                                <input
                                    id="auto_create"
                                    name="auto_create"
                                    type="checkbox"
                                    value="1"
                                    defaultChecked={bill.auto_create}
                                    className="size-4 rounded border-input"
                                />
                                <Label htmlFor="auto_create">
                                    Auto-create transaction when due
                                </Label>
                            </div>

                            {/* End type */}
                            <div className="grid gap-2">
                                <Label>End</Label>
                                <div className="flex flex-col gap-2">
                                    {(
                                        [
                                            'never',
                                            'on_date',
                                            'after_occurrences',
                                        ] as EndType[]
                                    ).map((value) => (
                                        <label
                                            key={value}
                                            className="flex cursor-pointer items-center gap-2"
                                        >
                                            <input
                                                type="radio"
                                                name="end_type"
                                                value={value}
                                                checked={endType === value}
                                                onChange={() =>
                                                    setEndType(value)
                                                }
                                                className="size-4"
                                            />
                                            <span className="text-sm capitalize">
                                                {value === 'never'
                                                    ? 'Never'
                                                    : value === 'on_date'
                                                      ? 'On date'
                                                      : 'After occurrences'}
                                            </span>
                                        </label>
                                    ))}
                                </div>
                                <InputError message={errors.end_type} />
                            </div>

                            {/* End date */}
                            {endType === 'on_date' && (
                                <div className="grid gap-2">
                                    <Label htmlFor="end_date">End date</Label>
                                    <Input
                                        id="end_date"
                                        name="end_date"
                                        type="date"
                                        defaultValue={
                                            bill.end_date ?? undefined
                                        }
                                        required
                                    />
                                    <InputError message={errors.end_date} />
                                </div>
                            )}

                            {/* End after occurrences */}
                            {endType === 'after_occurrences' && (
                                <div className="grid gap-2">
                                    <Label htmlFor="end_after_occurrences">
                                        End after occurrences
                                    </Label>
                                    <Input
                                        id="end_after_occurrences"
                                        name="end_after_occurrences"
                                        type="number"
                                        min="1"
                                        defaultValue={
                                            bill.end_after_occurrences ??
                                            undefined
                                        }
                                        required
                                    />
                                    <InputError
                                        message={errors.end_after_occurrences}
                                    />
                                </div>
                            )}

                            <Button disabled={processing}>Save changes</Button>
                        </>
                    )}
                </Form>
            </div>
        </AppLayout>
    );
}
