import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
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
import {
    create as createRoute,
    index as billsIndex,
} from '@/routes/ledgers/bills';
import type { Account, BreadcrumbItem, Category, Ledger, Payee } from '@/types';

type RecurrenceType = 'daily' | 'weekly' | 'monthly' | 'yearly' | 'custom';
type EndType = 'never' | 'on_date' | 'after_occurrences';

export default function CreateBill({
    ledger,
    accounts,
    categories,
    payees,
}: {
    ledger: Ledger;
    accounts: Account[];
    categories: Category[];
    payees: Payee[];
}) {
    const { data, setData, post, processing, errors, clearErrors } = useForm({
        name: '',
        transaction_type: 'expense',
        amount: '',
        account_id: accounts.length > 0 ? String(accounts[0].id) : '',
        category_id: '',
        payee_id: '',
        recurrence_type: 'monthly' as RecurrenceType,
        recurrence_interval: '1',
        recurrence_day: '',
        auto_create: false,
        end_type: 'never' as EndType,
        end_date: '',
        end_after_occurrences: '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        {
            title: 'Recurring Transactions',
            href: billsIndex.url(ledger.id),
        },
        { title: 'New', href: createRoute.url(ledger.id) },
    ];

    // Local UI state for select components that use __none__ sentinel
    const categorySelectValue = data.category_id === '' ? '__none__' : data.category_id;
    const payeeSelectValue = data.payee_id === '' ? '__none__' : data.payee_id;

    function submit(e: FormEvent) {
        e.preventDefault();
        post(BillController.store.url(ledger.id), {
            onSuccess: () => toast.success('Recurring transaction created'),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`New Recurring Transaction — ${ledger.name}`} />

            <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4">
                <Heading
                    title="New Recurring Transaction"
                    description="Set up a recurring expense or income for this ledger."
                />

                <form
                    onSubmit={submit}
                    className="space-y-6 rounded-xl border border-sidebar-border/70 p-6"
                >
                    {/* Name */}
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
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

                    {/* Transaction type */}
                    <div className="grid gap-2">
                        <Label>Type</Label>
                        <Select
                            value={data.transaction_type}
                            onValueChange={(value) => {
                                setData('transaction_type', value);
                                clearErrors('transaction_type');
                            }}
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
                            value={data.amount}
                            onChange={(e) => {
                                setData('amount', e.target.value);
                                clearErrors('amount');
                            }}
                            required
                        />
                        <InputError message={errors.amount} />
                    </div>

                    {/* Account */}
                    <div className="grid gap-2">
                        <Label>Account</Label>
                        <Select
                            value={data.account_id}
                            onValueChange={(value) => {
                                setData('account_id', value);
                                clearErrors('account_id');
                            }}
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
                            value={categorySelectValue}
                            onValueChange={(value) => {
                                setData('category_id', value === '__none__' ? '' : value);
                                clearErrors('category_id');
                            }}
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
                            value={payeeSelectValue}
                            onValueChange={(value) => {
                                setData('payee_id', value === '__none__' ? '' : value);
                                clearErrors('payee_id');
                            }}
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
                        <InputError message={errors.payee_id} />
                    </div>

                    {/* Recurrence */}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Recurrence type</Label>
                            <Select
                                value={data.recurrence_type}
                                onValueChange={(val) => {
                                    setData('recurrence_type', val as RecurrenceType);
                                    clearErrors('recurrence_type');
                                }}
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
                                value={data.recurrence_interval}
                                onChange={(e) => {
                                    setData('recurrence_interval', e.target.value);
                                    clearErrors('recurrence_interval');
                                }}
                                required
                            />
                            <InputError
                                message={errors.recurrence_interval}
                            />
                        </div>
                    </div>

                    {/* Recurrence day — only relevant for monthly/custom */}
                    {(data.recurrence_type === 'monthly' ||
                        data.recurrence_type === 'custom') && (
                        <div className="grid gap-2">
                            <Label htmlFor="recurrence_day">
                                Day of month{' '}
                                <span className="text-muted-foreground">
                                    (optional, 1-31)
                                </span>
                            </Label>
                            <Input
                                id="recurrence_day"
                                name="recurrence_day"
                                type="number"
                                inputMode="decimal"
                                min="1"
                                max="31"
                                value={data.recurrence_day}
                                onChange={(e) => {
                                    setData('recurrence_day', e.target.value);
                                    clearErrors('recurrence_day');
                                }}
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
                            checked={data.auto_create}
                            onCheckedChange={(checked) => {
                                setData('auto_create', checked === true);
                                clearErrors('auto_create');
                            }}
                        />
                        <Label htmlFor="auto_create">
                            Auto-create transaction when due
                        </Label>
                    </div>

                    {/* End type */}
                    <div className="grid gap-2">
                        <Label>End</Label>
                        <RadioGroup
                            value={data.end_type}
                            onValueChange={(val) => {
                                setData('end_type', val as EndType);
                                clearErrors('end_type');
                            }}
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
                        <InputError message={errors.end_type} />
                    </div>

                    {/* End date */}
                    {data.end_type === 'on_date' && (
                        <div className="grid gap-2">
                            <Label htmlFor="end_date">End date</Label>
                            <DatePicker
                                id="end_date"
                                name="end_date"
                                value={data.end_date}
                                onChange={(date) => {
                                    setData('end_date', date);
                                    clearErrors('end_date');
                                }}
                            />
                            <InputError message={errors.end_date} />
                        </div>
                    )}

                    {/* End after occurrences */}
                    {data.end_type === 'after_occurrences' && (
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
                                value={data.end_after_occurrences}
                                onChange={(e) => {
                                    setData('end_after_occurrences', e.target.value);
                                    clearErrors('end_after_occurrences');
                                }}
                                required
                            />
                            <InputError
                                message={errors.end_after_occurrences}
                            />
                        </div>
                    )}

                    <Button disabled={processing}>Create</Button>
                </form>
            </div>
        </AppLayout>
    );
}
