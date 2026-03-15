import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import BillController from '@/actions/App/Http/Controllers/Ledger/BillController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
    const [transactionType, setTransactionType] = useState<string>(
        bill.transaction_type ?? 'expense',
    );
    const [accountId, setAccountId] = useState(String(bill.account_id));
    const [categoryId, setCategoryId] = useState(
        bill.category_id ? String(bill.category_id) : '__none__',
    );
    const [payeeId, setPayeeId] = useState(
        bill.payee_id ? String(bill.payee_id) : '__none__',
    );
    const [autoCreate, setAutoCreate] = useState(bill.auto_create);
    const [endDate, setEndDate] = useState(bill.end_date ?? '');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        {
            title: 'Recurring Transactions',
            href: billsIndex.url(ledger.id),
        },
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
                    title="Edit Recurring Transaction"
                    description="Update the recurring transaction details."
                />

                <Form
                    {...BillController.update.form({
                        ledger: ledger.id,
                        bill: bill.id,
                    })}
                    className="space-y-6 rounded-xl border border-sidebar-border/70 p-6"
                    onSuccess={() =>
                        toast.success('Recurring transaction updated')
                    }
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
                                <Label>Type</Label>
                                <Select
                                    value={transactionType}
                                    onValueChange={setTransactionType}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Select type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="expense">
                                            Expense
                                        </SelectItem>
                                        <SelectItem value="income">
                                            Income
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <input
                                    type="hidden"
                                    name="transaction_type"
                                    value={transactionType}
                                />
                                <InputError message={errors.transaction_type} />
                            </div>

                            {/* Amount */}
                            <div className="grid gap-2">
                                <Label htmlFor="amount">Amount (RM)</Label>
                                <Input
                                    id="amount"
                                    name="amount"
                                    type="number"
                                    inputMode="decimal"
                                    step="0.01"
                                    min="0.01"
                                    defaultValue={bill.amount}
                                    required
                                />
                                <InputError message={errors.amount} />
                            </div>

                            {/* Account */}
                            <div className="grid gap-2">
                                <Label>Account</Label>
                                <Select
                                    value={accountId}
                                    onValueChange={setAccountId}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Select account" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {accounts.map((account) => (
                                            <SelectItem
                                                key={account.id}
                                                value={String(account.id)}
                                            >
                                                {account.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <input
                                    type="hidden"
                                    name="account_id"
                                    value={accountId}
                                />
                                <InputError message={errors.account_id} />
                            </div>

                            {/* Category — grouped by parent */}
                            <div className="grid gap-2">
                                <Label>
                                    Category{' '}
                                    <span className="text-muted-foreground">
                                        (optional)
                                    </span>
                                </Label>
                                <Select
                                    value={categoryId}
                                    onValueChange={setCategoryId}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="No category" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="__none__">
                                            No category
                                        </SelectItem>
                                        {categories.map((parent) =>
                                            parent.children &&
                                            parent.children.length > 0 ? (
                                                <SelectGroup key={parent.id}>
                                                    <SelectLabel>
                                                        {parent.name}
                                                    </SelectLabel>
                                                    <SelectItem
                                                        value={String(
                                                            parent.id,
                                                        )}
                                                    >
                                                        {parent.name} (general)
                                                    </SelectItem>
                                                    {parent.children.map(
                                                        (child) => (
                                                            <SelectItem
                                                                key={child.id}
                                                                value={String(
                                                                    child.id,
                                                                )}
                                                            >
                                                                {child.name}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectGroup>
                                            ) : (
                                                <SelectItem
                                                    key={parent.id}
                                                    value={String(parent.id)}
                                                >
                                                    {parent.name}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                <input
                                    type="hidden"
                                    name="category_id"
                                    value={
                                        categoryId === '__none__'
                                            ? ''
                                            : categoryId
                                    }
                                />
                                <InputError message={errors.category_id} />
                            </div>

                            {/* Payee */}
                            <div className="grid gap-2">
                                <Label>
                                    Payee{' '}
                                    <span className="text-muted-foreground">
                                        (optional)
                                    </span>
                                </Label>
                                <Select
                                    value={payeeId}
                                    onValueChange={setPayeeId}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="No payee" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="__none__">
                                            No payee
                                        </SelectItem>
                                        {payees.map((payee) => (
                                            <SelectItem
                                                key={payee.id}
                                                value={String(payee.id)}
                                            >
                                                {payee.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <input
                                    type="hidden"
                                    name="payee_id"
                                    value={
                                        payeeId === '__none__' ? '' : payeeId
                                    }
                                />
                                <InputError message={errors.payee_id} />
                            </div>

                            {/* Recurrence */}
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label>Recurrence type</Label>
                                    <Select
                                        value={recurrenceType}
                                        onValueChange={(val) =>
                                            setRecurrenceType(
                                                val as RecurrenceType,
                                            )
                                        }
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder="Select recurrence" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="daily">
                                                Daily
                                            </SelectItem>
                                            <SelectItem value="weekly">
                                                Weekly
                                            </SelectItem>
                                            <SelectItem value="monthly">
                                                Monthly
                                            </SelectItem>
                                            <SelectItem value="yearly">
                                                Yearly
                                            </SelectItem>
                                            <SelectItem value="custom">
                                                Custom
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <input
                                        type="hidden"
                                        name="recurrence_type"
                                        value={recurrenceType}
                                    />
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
                                        inputMode="decimal"
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
                                        inputMode="decimal"
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
                                <Checkbox
                                    id="auto_create"
                                    checked={autoCreate}
                                    onCheckedChange={(checked) =>
                                        setAutoCreate(checked === true)
                                    }
                                />
                                <input
                                    type="hidden"
                                    name="auto_create"
                                    value={autoCreate ? '1' : '0'}
                                />
                                <Label htmlFor="auto_create">
                                    Auto-create transaction when due
                                </Label>
                            </div>

                            {/* End type */}
                            <div className="grid gap-2">
                                <Label>End</Label>
                                <RadioGroup
                                    value={endType}
                                    onValueChange={(val) =>
                                        setEndType(val as EndType)
                                    }
                                >
                                    <div className="flex items-center gap-2">
                                        <RadioGroupItem
                                            value="never"
                                            id="end_type_never"
                                        />
                                        <Label htmlFor="end_type_never">
                                            Never
                                        </Label>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <RadioGroupItem
                                            value="on_date"
                                            id="end_type_on_date"
                                        />
                                        <Label htmlFor="end_type_on_date">
                                            On date
                                        </Label>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <RadioGroupItem
                                            value="after_occurrences"
                                            id="end_type_after_occurrences"
                                        />
                                        <Label htmlFor="end_type_after_occurrences">
                                            After occurrences
                                        </Label>
                                    </div>
                                </RadioGroup>
                                <input
                                    type="hidden"
                                    name="end_type"
                                    value={endType}
                                />
                                <InputError message={errors.end_type} />
                            </div>

                            {/* End date */}
                            {endType === 'on_date' && (
                                <div className="grid gap-2">
                                    <Label htmlFor="end_date">End date</Label>
                                    <DatePicker
                                        id="end_date"
                                        name="end_date"
                                        value={endDate}
                                        onChange={(date) => setEndDate(date)}
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
                                        inputMode="decimal"
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
